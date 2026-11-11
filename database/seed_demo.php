<?php
/**
 * Popula o banco com PEDIDOS DE DEMONSTRAÇÃO dos últimos 7 dias (para o dashboard, relatórios e
 * fechamento de caixa ficarem com números). Usa os mesmos Services do sistema, então estoque,
 * pagamentos e status seguem as regras reais.
 *
 *   php database/seed_demo.php           (só roda se ainda não houver pedidos)
 *   php database/seed_demo.php --force   (adiciona mesmo assim)
 *
 * Só para apresentação/testes. Não use em produção.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/PedidoService.php';
require_once __DIR__ . '/../src/Services/ContaService.php';

$pdo = Database::getConnection();
if ((int) $pdo->query('SELECT COUNT(*) FROM pedido')->fetchColumn() > 0 && !in_array('--force', $argv ?? [], true)) {
    fwrite(STDERR, "Já existem pedidos. Use --force para adicionar dados de demonstração mesmo assim.\n");
    exit(1);
}
mt_srand(42); // mesmos números a cada execução (apresentação previsível)

// Estoque folgado durante a geração; no fim deixamos 2 insumos baixos para o alerta aparecer.
$pdo->exec('UPDATE insumo SET estoque_atual = GREATEST(estoque_atual, estoque_minimo * 25)');

$formas = ['dinheiro', 'pix', 'pix', 'credito', 'debito'];
$canais = ['balcao', 'balcao', 'delivery_proprio', 'mesa'];
$criados = 0;


function novoPedido(string $canal, int $nItens, string $forma, ?int $mesa = null): array
{
    $itens = [];
    for ($i = 0; $i < $nItens; $i++) {
        $itens[] = ['id_produto' => mt_rand(1, 12), 'quantidade' => mt_rand(1, 2)];
    }
    $e = ['canal' => $canal, 'itens' => $itens, 'nome_cliente' => 'Cliente Demo', 'telefone_cliente' => '(+55)21-988887777',
          'endereco_entrega' => 'Rua das Flores, 100, Centro'];
    if ($mesa) { $e['id_mesa'] = $mesa; }
    $p = PedidoService::criarManual($e, null);
    return ['id' => (int) $p['id_pedido'], 'total' => (float) $p['valor_total'], 'canal' => $canal];
}

/**
 * Avança o pedido pelas etapas pedidas, pulando as que ele já passou
 * (pedido só com itens sem preparo — água, lata — já nasce entregue/pronto).
 */
function levarAte(int $id, array $etapas, ?int $entregador = null): void
{
    foreach ($etapas as $st) {
        if (in_array($st, PedidoService::buscar($id)['proximos'], true)) {
            PedidoService::mudarStatus($id, $st, null, $st === 'saiu' ? $entregador : null);
        }
    }
}

// ── Dias anteriores: pedidos completos (entregues e pagos), com datas retroativas ─────────────
for ($dia = 6; $dia >= 1; $dia--) {
    $qtd = mt_rand(4, 9);
    for ($k = 0; $k < $qtd; $k++) {
        $canal = $canais[array_rand($canais)];
        $mesa = $canal === 'mesa' ? mt_rand(1, 8) : null;
        $forma = $formas[array_rand($formas)];
        try {
            $p = novoPedido($canal, mt_rand(1, 3), $forma, $mesa);
            levarAte($p['id'], ['em preparo', 'pronto', 'saiu', 'entregue'], mt_rand(1, 3));
            PedidoService::pagar($p['id'], [['forma' => $forma, 'valor' => $p['total']]]);
            $hora = sprintf('%02d:%02d:00', mt_rand(11, 21), mt_rand(0, 59));
            $data = (new DateTimeImmutable("-$dia days"))->format('Y-m-d') . ' ' . $hora;
            $pdo->prepare('UPDATE pedido SET data_hora = :d1, data_hora_inicio_preparo = :d2 WHERE id_pedido = :i')->execute(['d1' => $data, 'd2' => $data, 'i' => $p['id']]);
            $pdo->prepare('UPDATE pagamento SET data_hora = :d WHERE id_pedido = :i')->execute(['d' => $data, 'i' => $p['id']]);
            $pdo->prepare('UPDATE movimento_estoque SET data_hora = :d WHERE motivo LIKE :m')->execute(['d' => $data, 'm' => '%#' . $p['id'] . '%']);
            $criados++;
        } catch (Throwable $e) {
            fwrite(STDERR, 'Pedido ignorado: ' . $e->getMessage() . "\n");
        }
    }
}

// ── Hoje: alguns em cada etapa do kanban ──────────────────────────────────────────────────────
$hoje = [
    ['balcao', 'novo', null], ['delivery_proprio', 'novo', null],
    ['balcao', 'em preparo', null], ['mesa', 'em preparo', 3],
    ['delivery_proprio', 'pronto', null], ['mesa', 'pronto', 5],
    ['delivery_proprio', 'saiu', null], ['balcao', 'entregue', null],
];
foreach ($hoje as [$canal, $alvo, $mesa]) {
    try {
        $forma = $formas[array_rand($formas)];
        $p = novoPedido($canal, mt_rand(1, 3), $forma, $mesa);
        $fluxo = ['novo' => [], 'em preparo' => ['em preparo'], 'pronto' => ['em preparo', 'pronto'],
                  'saiu' => ['em preparo', 'pronto', 'saiu'], 'entregue' => ['em preparo', 'pronto', 'entregue']];
        levarAte($p['id'], $fluxo[$alvo], 1);
        if ($alvo === 'entregue' || $canal === 'delivery_proprio') {
            PedidoService::pagar($p['id'], [['forma' => $forma, 'valor' => $p['total']]]);
        }
        $criados++;
    } catch (Throwable $e) {
        fwrite(STDERR, 'Pedido de hoje ignorado: ' . $e->getMessage() . "\n");
    }
}
// Um pedido "em preparo" há 35 minutos: aparece como ATRASADO na cozinha.
try {
    $p = novoPedido('balcao', 2, 'pix');
    levarAte($p['id'], ['em preparo']);
    $pdo->prepare('UPDATE pedido SET data_hora_inicio_preparo = DATE_SUB(NOW(), INTERVAL 35 MINUTE) WHERE id_pedido = :i')->execute(['i' => $p['id']]);
    $criados++;
} catch (Throwable $e) { fwrite(STDERR, 'Pedido atrasado ignorado: ' . $e->getMessage() . "\n"); }


// ── Pedidos de plataformas (iFood/Rappi) → geram contas a receber (repasse) ───────────────────
$externos = [['ifood', 35], ['rappi', 20], ['ifood', 10], ['ifood', 2], ['rappi', 0]];
foreach ($externos as $k => [$plat, $diasAtras]) {
    try {
        $r = PedidoService::receberExterno(['plataforma' => $plat, 'id_pedido_externo' => 'DEMO-' . ($k + 1),
            'cliente' => ['nome' => 'Cliente ' . strtoupper($plat), 'telefone' => '(+55)21-988887777'], 'endereco' => 'Rua das Flores, 100, Centro',
            'itens' => [['id_produto' => mt_rand(1, 6), 'quantidade' => mt_rand(1, 3)]], 'forma_pagamento' => 'pix']);
        $id = (int) $r['id_pedido'];
        levarAte($id, ['em preparo', 'pronto', 'saiu', 'entregue'], 2);
        if ($diasAtras > 0) {
            $data = (new DateTimeImmutable("-$diasAtras days"))->format('Y-m-d') . ' 19:30:00';
            $pdo->prepare('UPDATE pedido SET data_hora = :d1, data_hora_inicio_preparo = :d2 WHERE id_pedido = :i')->execute(['d1' => $data, 'd2' => $data, 'i' => $id]);
            $pdo->prepare('UPDATE pagamento SET data_hora = :d WHERE id_pedido = :i')->execute(['d' => $data, 'i' => $id]);
            $prazo = (int) $pdo->query("SELECT prazo_repasse_dias FROM canal_venda WHERE codigo = '$plat'")->fetchColumn();
            $venc = (new DateTimeImmutable($data))->modify("+$prazo days")->format('Y-m-d');
            $pdo->prepare('UPDATE conta_financeira SET data_emissao = :e, data_vencimento = :v WHERE id_pedido = :i')->execute(['e' => substr($data, 0, 10), 'v' => $venc, 'i' => $id]);
        }
        if ($k === 1) { // o repasse do Rappi de 20 dias atrás já caiu
            $idConta = (int) $pdo->query("SELECT id_conta FROM conta_financeira WHERE id_pedido = $id")->fetchColumn();
            ContaService::baixar($idConta, null, null, null);
        }
        $criados++;
    } catch (Throwable $e) { fwrite(STDERR, 'Pedido de plataforma ignorado: ' . $e->getMessage() . "\n"); }
}

// ── Contas a pagar de exemplo ──────────────────────────────────────────────────────────────────
$d = fn(int $n) => (new DateTimeImmutable(($n >= 0 ? "+$n" : "$n") . ' days'))->format('Y-m-d');
$contas = [
    ['Aluguel do mês', 'aluguel', 'Imobiliária Central', '1500,00', $d(5), false],
    ['Conta de energia', 'energia', 'Light', '640,50', $d(-3), false],           // vencida
    ['Internet e telefone', 'internet', 'Operadora Net', '189,90', $d(8), false],
    ['Compra de carnes e queijos', 'insumos', 'Frigorífico Boi Bom', '950,00', $d(-6), true],  // já paga
    ['Folha de pagamento', 'folha', 'Equipe', '1800,00', $d(12), false],
];
foreach ($contas as [$desc, $cat, $forn, $valor, $venc, $paga]) {
    try {
        $c = ContaService::salvar(['tipo' => 'pagar', 'descricao' => $desc, 'categoria' => $cat, 'pessoa' => $forn, 'valor' => $valor, 'data_vencimento' => $venc]);
        if ($paga) { ContaService::baixar($c['id_conta'], null, null, null); }
    } catch (Throwable $e) { fwrite(STDERR, 'Conta ignorada: ' . $e->getMessage() . "\n"); }
}

// Dois insumos abaixo do mínimo, para mostrar o alerta de estoque (RF16).
$pdo->exec("UPDATE insumo SET estoque_atual = estoque_minimo * 0.5 WHERE nome LIKE 'Bacon%' OR nome LIKE 'Queijo cheddar%'");

echo "Pronto: $criados pedidos de demonstração criados.\n";
