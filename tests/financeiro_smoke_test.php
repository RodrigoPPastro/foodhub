<?php
/**
 * Teste automatizado do módulo Financeiro (RF22–RF26), com SQLite em memória
 * no lugar do MySQL (não precisa de XAMPP). Rodar com:
 *
 *   php tests/financeiro_smoke_test.php
 */

if (!class_exists('Database')) {
    class Database
    {
        private static ?PDO $instance = null;

        public static function getConnection(): PDO
        {
            if (self::$instance === null) {
                $pdo = new PDO('sqlite::memory:');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->exec("
                    CREATE TABLE usuario (id_usuario INTEGER PRIMARY KEY AUTOINCREMENT, nome_completo TEXT, login TEXT,
                        email TEXT, telefone_celular TEXT, cpf TEXT, perfil TEXT, senha_hash TEXT, data_cadastro TEXT);
                    CREATE TABLE produto (id_produto INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT);
                    CREATE TABLE insumo (id_insumo INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, unidade_medida TEXT,
                        estoque_atual REAL, estoque_minimo REAL, custo_unitario REAL);
                    CREATE TABLE produto_insumo (id_produto INTEGER, id_insumo INTEGER, quantidade_utilizada REAL);
                    CREATE TABLE pedido (id_pedido INTEGER PRIMARY KEY AUTOINCREMENT, data_hora TEXT, canal TEXT,
                        status TEXT, valor_total REAL, id_usuario_cliente INTEGER, id_mesa INTEGER, id_entregador INTEGER,
                        plataforma_externa TEXT, id_pedido_externo TEXT, nome_cliente TEXT, telefone_cliente TEXT,
                        endereco_entrega TEXT, observacao TEXT, estoque_baixado INTEGER DEFAULT 0,
                        data_hora_inicio_preparo TEXT, sincronizado_status_externo INTEGER DEFAULT 0, id_garcom INTEGER);
                    CREATE TABLE garcom (id_garcom INTEGER PRIMARY KEY, nome TEXT, ativo INTEGER DEFAULT 1);
                    -- tabelas que o painel de pedidos (dashboard) consulta junto com o pedido
                    CREATE TABLE canal_venda (codigo TEXT PRIMARY KEY, rotulo TEXT, presencial INTEGER, externo INTEGER, ativo INTEGER DEFAULT 1,
                        comissao_percentual REAL DEFAULT 0, prazo_repasse_dias INTEGER DEFAULT 0);
                    CREATE TABLE conta_financeira (id_conta INTEGER PRIMARY KEY AUTOINCREMENT, tipo TEXT, descricao TEXT, categoria TEXT, pessoa TEXT,
                        valor REAL, valor_bruto REAL, comissao_percentual REAL, data_emissao TEXT, data_vencimento TEXT, data_baixa TEXT,
                        status TEXT DEFAULT 'aberta', id_pedido INTEGER UNIQUE, id_usuario_baixa INTEGER, observacao TEXT);
                    CREATE TABLE mesa (id_mesa INTEGER PRIMARY KEY AUTOINCREMENT, numero INTEGER);
                    CREATE TABLE entregador (id_entregador INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT);
                    CREATE TABLE notificacao_cliente (id_notificacao INTEGER PRIMARY KEY AUTOINCREMENT, id_pedido INTEGER,
                        tipo TEXT, canal TEXT, mensagem TEXT, data_hora_envio TEXT, status_envio TEXT);
                    CREATE TABLE item_pedido (id_item INTEGER PRIMARY KEY AUTOINCREMENT, id_pedido INTEGER,
                        id_produto INTEGER, quantidade INTEGER, preco_unitario REAL, observacao TEXT,
                        setor_preparo TEXT DEFAULT 'cozinha', status_item TEXT DEFAULT 'aguardando', data_hora_inicio_preparo TEXT);
                    CREATE TABLE forma_pagamento (id_forma_pagamento INTEGER PRIMARY KEY AUTOINCREMENT, descricao TEXT);
                    CREATE TABLE pagamento (id_pagamento INTEGER PRIMARY KEY AUTOINCREMENT, id_pedido INTEGER,
                        id_forma_pagamento INTEGER, valor REAL, data_hora TEXT);
                    CREATE TABLE fechamento_caixa (id_fechamento INTEGER PRIMARY KEY AUTOINCREMENT,
                        id_usuario_responsavel INTEGER, data_referencia TEXT UNIQUE, total_vendas REAL,
                        total_dinheiro REAL, total_cartao REAL, total_pix REAL, total_delivery REAL,
                        observacoes TEXT, detalhamento TEXT);
                ");
                self::$instance = $pdo;
            }
            return self::$instance;
        }
    }
}

require_once __DIR__ . '/../src/Controllers/PagamentoController.php';
require_once __DIR__ . '/../src/Controllers/FechamentoCaixaController.php';
require_once __DIR__ . '/../src/Controllers/RelatorioController.php';
require_once __DIR__ . '/../src/Controllers/DashboardController.php';
require_once __DIR__ . '/../src/Controllers/UsuarioExportacaoController.php';

$falhou = false;
function check($cond, string $label): void
{
    global $falhou;
    echo ($cond ? '[OK]   ' : '[FALHA] ') . $label . PHP_EOL;
    if (!$cond) { $falhou = true; }
}

$pdo  = Database::getConnection();
$hoje = PeriodoService::hoje();
$ontem = (new DateTimeImmutable($hoje))->modify('-1 day')->format('Y-m-d');

// ---- Massa de dados ----------------------------------------------------
foreach (['dinheiro', 'pix', 'credito', 'debito', 'vale'] as $f) {
    $pdo->exec("INSERT INTO forma_pagamento (descricao) VALUES ('$f')");
}
$pdo->exec("INSERT INTO produto (nome) VALUES ('X-Burger'), ('Batata <b>Frita</b>')");
$pdo->exec("INSERT INTO insumo (nome, unidade_medida, estoque_atual, estoque_minimo, custo_unitario) VALUES
    ('Pão', 'un', 5, 20, 1.50), ('Carne', 'g', 9000, 2000, 0.04)");
$pdo->exec("INSERT INTO produto_insumo VALUES (1, 1, 1), (1, 2, 150), (2, 1, 0)");
// Pedidos de hoje: 1 balcão 50,00 | 2 delivery próprio 30,00 | 3 ifood 20,00 | 4 cancelado | 5 em aberto sem pagamento
$pdo->exec("INSERT INTO pedido (data_hora, canal, status, valor_total) VALUES
    ('$hoje 12:00:00', 'balcao', 'entregue', 50.00),
    ('$hoje 13:00:00', 'delivery_proprio', 'entregue', 30.00),
    ('$hoje 14:00:00', 'ifood', 'entregue', 20.00),
    ('$hoje 15:00:00', 'balcao', 'cancelado', 99.00),
    ('$hoje 16:00:00', 'balcao', 'novo', 10.00),
    ('$ontem 12:00:00', 'balcao', 'entregue', 40.00)");
$pdo->exec("INSERT INTO item_pedido (id_pedido, id_produto, quantidade, preco_unitario) VALUES
    (1, 1, 2, 25.00), (2, 1, 1, 30.00), (3, 2, 2, 10.00), (6, 1, 1, 40.00)");

echo "== RF22 pagamentos ==" . PHP_EOL;
$r = PagamentoController::registrar(['id_pedido' => 1, 'pagamentos' => [
    ['forma' => 'dinheiro', 'valor' => 20], ['forma' => 'pix', 'valor' => '30,00'],
]]);
check($r['sucesso'] && $r['dados']['quitado'] && $r['dados']['saldo'] == 0, 'conta dividida (dinheiro + pix) quita o pedido');

$r = PagamentoController::registrar(['id_pedido' => 1, 'pagamentos' => [['forma' => 'pix', 'valor' => 1]]]);
check(!$r['sucesso'] && $r['http'] === 422, 'rejeita pagamento acima do saldo: ' . ($r['mensagem'] ?? ''));

$r = PagamentoController::registrar(['id_pedido' => 4, 'pagamentos' => [['forma' => 'pix', 'valor' => 10]]]);
check(!$r['sucesso'], 'rejeita pagamento em pedido cancelado');

$r = PagamentoController::registrar(['id_pedido' => 2, 'pagamentos' => [['forma' => 'bitcoin', 'valor' => 10]]]);
check(!$r['sucesso'], 'rejeita forma de pagamento inválida');

$r = PagamentoController::registrar(['id_pedido' => 2, 'pagamentos' => [['forma' => 'pix', 'valor' => -5]]]);
check(!$r['sucesso'], 'rejeita valor negativo');

$r = PagamentoController::registrar(['id_pedido' => 2, 'pagamentos' => [
    ['forma' => 'credito', 'valor' => 10], ['forma' => 'pix', 'valor' => 999],
]]);
check(!$r['sucesso'] && (int) $pdo->query('SELECT COUNT(*) FROM pagamento WHERE id_pedido = 2')->fetchColumn() === 0,
    'transação: lote inválido não grava nada');

PagamentoController::registrar(['id_pedido' => 2, 'pagamentos' => [['forma' => 'credito', 'valor' => 30]]]);
PagamentoController::registrar(['id_pedido' => 3, 'pagamentos' => [['forma' => 'debito', 'valor' => 12], ['forma' => 'vale', 'valor' => 8]]]);
$r = PagamentoController::consultar(['id_pedido' => 3]);
check($r['sucesso'] && count($r['dados']['pagamentos']) === 2, 'consulta pagamentos do pedido');

echo "== RF23 fechamento de caixa ==" . PHP_EOL;
$r = FechamentoCaixaController::consultar(['data' => $hoje]);
check($r['sucesso'] && $r['dados']['total_geral'] == 100.0, 'total geral = 100,00');
$formas = array_column($r['dados']['por_forma'], 'total', 'forma');
check($formas['dinheiro'] == 20 && $formas['pix'] == 30 && $formas['credito'] == 30 && $formas['debito'] == 12 && $formas['vale'] == 8, 'totais por forma de pagamento');
$canais = array_column($r['dados']['por_canal'], 'total', 'canal');
check($canais['balcao'] == 50 && $canais['delivery_proprio'] == 30 && $canais['ifood'] == 20, 'totais por canal');
check(count($r['dados']['pendencias']) === 1 && !$r['dados']['pode_fechar'], 'pedido 5 (sem pagamento) bloqueia o fechamento');

$r = FechamentoCaixaController::fechar(1, ['data' => $hoje, 'observacoes' => 'x']);
check(!$r['sucesso'], 'não fecha com pendência: ' . ($r['mensagem'] ?? ''));

$pdo->exec("UPDATE pedido SET status = 'cancelado' WHERE id_pedido = 5");
$r = FechamentoCaixaController::fechar(1, ['data' => $hoje, 'observacoes' => 'Sem diferenças']);
check($r['sucesso'] && $r['dados']['fechado'], 'fecha o caixa');
$linha = $pdo->query('SELECT * FROM fechamento_caixa')->fetch();
check((float) $linha['total_cartao'] == 42 && (float) $linha['total_delivery'] == 50 && (float) $linha['total_vendas'] == 100,
    'colunas do DER: cartão = crédito+débito (42), delivery = própria+ifood (50)');
$r = FechamentoCaixaController::fechar(1, ['data' => $hoje]);
check(!$r['sucesso'], 'não permite fechar o mesmo dia duas vezes');
$r = FechamentoCaixaController::fechar(1, ['data' => '2999-01-01']);
check(!$r['sucesso'], 'não permite data futura');
$r = FechamentoCaixaController::consultar(['data' => '2026-02-30']);
check(!$r['sucesso'] && $r['http'] === 422, 'data inexistente é rejeitada');

$pdf = FechamentoCaixaController::pdf(['data' => $hoje]);
check($pdf['sucesso'] && str_starts_with($pdf['dados']['conteudo'], '%PDF') && str_ends_with(trim($pdf['dados']['conteudo']), '%%EOF'), 'PDF do fechamento gerado');
file_put_contents('/tmp/fechamento.pdf', $pdf['dados']['conteudo']);

echo "== RF24 relatórios ==" . PHP_EOL;
$r = RelatorioController::gerar(['relatorio' => 'ticket', 'inicio' => $ontem, 'fim' => $hoje]);
check($r['sucesso'] && $r['dados']['pedidos'] === 4 && $r['dados']['ticket_medio'] == 35.0, 'ticket médio = 140/4 = 35,00 (cancelados fora)');
$r = RelatorioController::gerar(['relatorio' => 'vendas', 'inicio' => $ontem, 'fim' => $hoje]);
check($r['sucesso'] && count($r['dados']['linhas']) === 2 && $r['dados']['totais']['total'] == 140.0, 'vendas por período');
$r = RelatorioController::gerar(['relatorio' => 'produtos', 'inicio' => $ontem, 'fim' => $hoje, 'limite' => 5]);
check($r['dados']['linhas'][0]['nome'] === 'X-Burger' && $r['dados']['linhas'][0]['quantidade'] === 4, 'produto mais vendido (4 un.)');
$r = RelatorioController::gerar(['relatorio' => 'insumos', 'inicio' => $hoje, 'fim' => $hoje]);
$ins = array_column($r['dados']['linhas'], 'consumo', 'nome');
check($ins['Pão'] == 3.0 && $ins['Carne'] == 450.0, 'consumo de insumos pela ficha técnica');
$r = RelatorioController::gerar(['relatorio' => 'canais', 'inicio' => $hoje, 'fim' => $hoje]);
check($r['dados']['linhas'][0]['canal'] === 'balcao' && $r['dados']['linhas'][0]['participacao_percentual'] == 50.0, 'comparativo: balcão = 50% (cancelado fora)');
check($r['dados']['linhas'][1]['diferenca_vs_lider'] == -20.0, 'comparativo absoluto vs líder');
$r = RelatorioController::gerar(['relatorio' => 'vendas', 'inicio' => $hoje, 'fim' => $ontem]);
check(!$r['sucesso'], 'período invertido é rejeitado');
$r = RelatorioController::gerar(['relatorio' => 'DROP TABLE']);
check(!$r['sucesso'], 'tipo de relatório fora da whitelist é rejeitado');

echo "== RF25 dashboard ==" . PHP_EOL;
$r = DashboardController::indicadores();
$i = $r['dados']['indicadores'];
check($r['sucesso'] && $i['vendas_hoje']['valor'] == 100.0 && $i['vendas_hoje']['pedidos'] === 3, 'vendas do dia');
check($i['ticket_medio'] == 33.33, 'ticket médio do dia');
check($i['pedidos_em_aberto'] === 0, 'pedidos em aberto');
check($i['estoque_baixo']['quantidade'] === 1 && $i['estoque_baixo']['itens'][0]['nome'] === 'Pão', 'estoque baixo (Pão 5 < 20)');
check(count($r['dados']['graficos']['vendas_diarias']['labels']) === 7, 'gráfico de 7 dias com dias vazios preenchidos');

echo "== RF26 usuários em PDF ==" . PHP_EOL;
$pdo->exec("INSERT INTO usuario (nome_completo, login, email, telefone_celular, cpf, perfil, senha_hash, data_cadastro) VALUES
    ('Ação José Çedilha', 'master', 'm@x.com', '(+55)21-999999999', '12345678909', 'master', 'SEGREDO', '2026-01-02 10:00:00')");
$u = UsuarioExportacaoController::pdf('Master');
check($u['sucesso'] && str_starts_with($u['dados']['conteudo'], '%PDF'), 'PDF de usuários gerado (acentos ok)');
check(strpos($u['dados']['conteudo'], 'SEGREDO') === false, 'hash de senha não vaza para o PDF');
file_put_contents('/tmp/usuarios.pdf', $u['dados']['conteudo']);

echo PHP_EOL . ($falhou ? 'HÁ FALHAS.' : 'Todos os testes passaram.') . PHP_EOL;
exit($falhou ? 1 : 0);
