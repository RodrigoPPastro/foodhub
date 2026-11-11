<?php
/**
 * Teste de integração dos módulos Cardápio/Estoque (Fernanda) e Pedidos/Delivery (Pedro),
 * rodando contra um MySQL/MariaDB REAL (banco descartável "foodhub_teste", criado a partir de
 * database/foodhub_completo.sql). Não toca no banco "foodhub".
 *
 *   php tests/modulos_test.php          (usa root sem senha em 127.0.0.1; ajuste com FOODHUB_DB_*)
 */
putenv('FOODHUB_DB_NAME=foodhub_teste');
define('FOODHUB_TESTE', true);

$host = getenv('FOODHUB_DB_HOST') ?: '127.0.0.1';
$raiz = new PDO("mysql:host=$host;port=" . (getenv('FOODHUB_DB_PORT') ?: '3306') . ';charset=utf8mb4',
    getenv('FOODHUB_DB_USER') ?: 'root', getenv('FOODHUB_DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$raiz->exec('DROP DATABASE IF EXISTS foodhub_teste');
$sql = file_get_contents(__DIR__ . '/../database/foodhub_completo.sql');
$sql = preg_replace('/CREATE DATABASE IF NOT EXISTS foodhub /', 'CREATE DATABASE IF NOT EXISTS foodhub_teste ', $sql, 1);
$sql = preg_replace('/^USE foodhub;/m', 'USE foodhub_teste;', $sql, 1);
$raiz->exec($sql);

require_once __DIR__ . '/../src/Controllers/CategoriaController.php';
require_once __DIR__ . '/../src/Controllers/ProdutoController.php';
require_once __DIR__ . '/../src/Controllers/InsumoController.php';
require_once __DIR__ . '/../src/Controllers/PedidoController.php';
require_once __DIR__ . '/../src/Controllers/DashboardController.php';
require_once __DIR__ . '/../src/Controllers/FechamentoCaixaController.php';
require_once __DIR__ . '/../src/Controllers/ContaController.php';

$falhou = false;
function check($cond, string $label): void { global $falhou; echo ($cond ? '[OK]   ' : '[FALHA] ') . $label . PHP_EOL; if (!$cond) { $falhou = true; } }
function erroDe(array $r): string { return $r['mensagem'] ?? ''; }

$pdo = Database::getConnection();
$q = fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s; })();
$estoque = fn(int $id) => (float) $q('SELECT estoque_atual FROM insumo WHERE id_insumo = ?', [$id])->fetchColumn();
$MASTER = (int) $q("SELECT id_usuario FROM usuario WHERE login = 'master'")->fetchColumn();
$CLIENTE = (int) $q("SELECT id_usuario FROM usuario WHERE login = 'client'")->fetchColumn();

echo "== RF13 categorias ==" . PHP_EOL;
$r = CategoriaController::salvar(['nome' => 'Saladas', 'descricao' => 'Leves']);
check($r['sucesso'], 'cria categoria'); $idCat = $r['dados']['id_categoria'];
check(!CategoriaController::salvar(['nome' => 'saladas'])['sucesso'], 'rejeita nome duplicado (sem diferenciar maiúsculas)');
check(!CategoriaController::salvar(['nome' => 'A'])['sucesso'], 'rejeita nome curto');
check(!CategoriaController::excluir(['id_categoria' => 1])['sucesso'], 'não exclui categoria com produtos');
check(CategoriaController::excluir(['id_categoria' => $idCat])['sucesso'], 'exclui categoria vazia');

echo "== RF13/RF14 produtos e ficha técnica ==" . PHP_EOL;
$r = ProdutoController::salvar(['nome' => 'Salada Hub', 'id_categoria' => 3, 'preco_venda' => '19,90', 'descricao' => 'x', 'ativo' => 1]);
check($r['sucesso'], 'cria produto (preço "19,90")'); $idProd = $r['dados']['id_produto'];
check(!ProdutoController::salvar(['nome' => 'Zero', 'id_categoria' => 3, 'preco_venda' => '0'])['sucesso'], 'rejeita preço zero');
check(!ProdutoController::salvar(['nome' => 'Sem cat', 'id_categoria' => 999, 'preco_venda' => '5'])['sucesso'], 'rejeita categoria inexistente');
$r = ProdutoController::salvarFicha(['id_produto' => $idProd, 'itens' => [['id_insumo' => 5, 'quantidade' => '0,2'], ['id_insumo' => 6, 'quantidade' => 0.1]]]);
// custo = 0,2*12 + 0,1*8 = 3,20 ; margem = 19,90-3,20 = 16,70 (83,9%)
check($r['sucesso'] && $r['dados']['custo'] == 3.2 && $r['dados']['margem'] == 16.7 && $r['dados']['margem_percentual'] == 83.9, 'custo do prato e margem calculados (3,20 / 16,70 / 83,9%)');
check(!ProdutoController::salvarFicha(['id_produto' => $idProd, 'itens' => [['id_insumo' => 5, 'quantidade' => 0], ]])['sucesso'], 'ficha rejeita quantidade zero');
check(!ProdutoController::salvarFicha(['id_produto' => $idProd, 'itens' => [['id_insumo' => 5, 'quantidade' => 1], ['id_insumo' => 5, 'quantidade' => 2]]])['sucesso'], 'ficha rejeita insumo repetido');
$lista = ProdutoController::listar(['busca' => 'Salada']);
check($lista['sucesso'] && count($lista['dados']) === 1 && $lista['dados'][0]['custo'] == 3.2, 'lista com custo/margem e busca');
$card = ProdutoController::cardapio();
$achou = false; foreach ($card['dados'] as $g) { foreach ($g['produtos'] as $p) { if ($p['id_produto'] === $idProd) { $achou = true; check(!isset($p['custo']) && !isset($p['margem']), 'cardápio público não expõe custo/margem'); } } }
check($achou, 'cardápio traz produto ativo');
ProdutoController::definirAtivo(['id_produto' => $idProd, 'ativo' => false]);
$ids = []; foreach (ProdutoController::cardapio()['dados'] as $g) { foreach ($g['produtos'] as $p) { $ids[] = $p['id_produto']; } }
check(!in_array($idProd, $ids, true), 'produto inativo some do cardápio');
ProdutoController::definirAtivo(['id_produto' => $idProd, 'ativo' => true]);

// foto
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$tmp = tempnam(sys_get_temp_dir(), 'png'); file_put_contents($tmp, $png);
$r = ProdutoController::foto($idProd, ['error' => UPLOAD_ERR_OK, 'tmp_name' => $tmp, 'size' => strlen($png), 'name' => 'x.png']);
check($r['sucesso'] && str_starts_with($r['dados']['foto'], 'uploads/produtos/') && is_file(__DIR__ . '/../public/' . $r['dados']['foto']), 'upload de foto PNG');
$tmp2 = tempnam(sys_get_temp_dir(), 'php'); file_put_contents($tmp2, '<?php echo 1;');
check(!ProdutoController::foto($idProd, ['error' => UPLOAD_ERR_OK, 'tmp_name' => $tmp2, 'size' => 13, 'name' => 'x.png'])['sucesso'], 'rejeita arquivo que não é imagem (mesmo com nome .png)');
ProdutoController::removerFoto($idProd);
array_map('unlink', glob(__DIR__ . '/../public/uploads/produtos/produto_*') ?: []);

echo "== RF15/RF16 insumos e estoque ==" . PHP_EOL;
$r = InsumoController::salvar(['nome' => 'Picles', 'unidade_medida' => 'kg', 'estoque_atual' => '2,5', 'estoque_minimo' => 1, 'custo_unitario' => '10'], $MASTER);
check($r['sucesso'], 'cria insumo com estoque inicial'); $idIns = $r['dados']['id_insumo'];
check($estoque($idIns) == 2.5 && (int) $q('SELECT COUNT(*) FROM movimento_estoque WHERE id_insumo = ?', [$idIns])->fetchColumn() === 1, 'estoque inicial gera movimento de entrada');
check(!InsumoController::movimentar(['id_insumo' => $idIns, 'tipo' => 'saida', 'quantidade' => 3], $MASTER)['sucesso'] && $estoque($idIns) == 2.5, 'saída acima do saldo é recusada e nada muda');
check(!InsumoController::movimentar(['id_insumo' => $idIns, 'tipo' => 'perda', 'quantidade' => 1], $MASTER)['sucesso'], 'perda exige motivo');
check(InsumoController::movimentar(['id_insumo' => $idIns, 'tipo' => 'perda', 'quantidade' => 1.8, 'motivo' => 'vencido'], $MASTER)['sucesso'] && $estoque($idIns) == 0.7, 'perda registrada (2,5 - 1,8 = 0,7)');
$l = InsumoController::listar(['baixo' => '1']);
check($l['sucesso'] && in_array('Picles', array_column($l['dados']['insumos'], 'nome'), true), 'RF16: insumo abaixo do mínimo aparece no alerta');
check(!InsumoController::excluir(['id_insumo' => 5])['sucesso'], 'não exclui insumo usado em ficha técnica');

echo "== RF17/RF18 criação de pedidos ==" . PHP_EOL;
// X-Burger (24,90) x2 + Refrigerante (6,50) x1 = 56,30
$itens = [['id_produto' => 1, 'quantidade' => 2], ['id_produto' => 9, 'quantidade' => 1]];
$r = PedidoController::criarManual(['canal' => 'balcao', 'itens' => $itens, 'nome_cliente' => 'Joana', 'telefone_cliente' => '(+55)21-988887777'], $MASTER);
check($r['sucesso'] && $r['dados']['valor_total'] == 56.3 && $r['dados']['status'] === 'novo', 'pedido de balcão: total 56,30 e status "novo"'); $p1 = $r['dados']['id_pedido'];
check((int) $q("SELECT COUNT(*) FROM notificacao_cliente WHERE id_pedido = ? AND tipo = 'confirmacao'", [$p1])->fetchColumn() === 1, 'notificação de confirmação registrada (WhatsApp simulado)');
$r = PedidoController::criarManual(['canal' => 'balcao', 'itens' => [['id_produto' => 1, 'quantidade' => 100]]], $MASTER);
check(!$r['sucesso'], 'rejeita quantidade 100');
check(!PedidoController::criarManual(['canal' => 'balcao', 'itens' => []], $MASTER)['sucesso'], 'rejeita pedido sem itens');
check(!PedidoController::criarManual(['canal' => 'mesa', 'itens' => $itens], $MASTER)['sucesso'], 'canal mesa exige mesa');
check(!PedidoController::criarManual(['canal' => 'delivery_proprio', 'itens' => $itens, 'telefone_cliente' => '(+55)21-988887777'], $MASTER)['sucesso'], 'delivery exige endereço');
check(!PedidoController::criarManual(['canal' => 'ifood', 'itens' => $itens], $MASTER)['sucesso'], 'canal externo não é lançado à mão');
check(!PedidoController::criarManual(['canal' => 'xyz', 'itens' => $itens], $MASTER)['sucesso'], 'canal inexistente é recusado');
$q('UPDATE produto SET ativo = 0 WHERE id_produto = 12');
check(!PedidoController::criarManual(['canal' => 'balcao', 'itens' => [['id_produto' => 12, 'quantidade' => 1]]], $MASTER)['sucesso'], 'produto inativo não pode ser vendido');
$q('UPDATE produto SET ativo = 1 WHERE id_produto = 12');

echo "== RF17 fluxo de status + RF15 baixa automática ==" . PHP_EOL;
$antesPao = $estoque(1); $antesCarne = $estoque(2); $antesRefri = $estoque(11);
check(!PedidoController::mudarStatus(['id_pedido' => $p1, 'status' => 'pronto'], $MASTER)['sucesso'], 'não pula etapa (novo → pronto)');
check(!PedidoController::mudarStatus(['id_pedido' => $p1, 'status' => 'saiu'], $MASTER)['sucesso'], 'balcão não "sai para entrega"');
$r = PedidoController::mudarStatus(['id_pedido' => $p1, 'status' => 'em preparo'], $MASTER);
check($r['sucesso'] && $r['dados']['status'] === 'em preparo', 'novo → em preparo');
// 2 hambúrgueres: pão -2, carne -0,300 ; refri -1
check($estoque(1) == $antesPao - 2 && abs($estoque(2) - ($antesCarne - 0.3)) < 1e-9 && $estoque(11) == $antesRefri - 1, 'baixa automática pela ficha técnica (pão -2, carne -0,3 kg, refri -1)');
check($q('SELECT data_hora_inicio_preparo FROM pedido WHERE id_pedido = ?', [$p1])->fetchColumn() !== null, 'registra horário de início do preparo');
PedidoController::mudarStatus(['id_pedido' => $p1, 'status' => 'pronto'], $MASTER);
check((int) $q("SELECT COUNT(*) FROM notificacao_cliente WHERE id_pedido = ? AND tipo = 'pronto'", [$p1])->fetchColumn() === 1, 'notificação "pedido pronto"');
$r = PedidoController::mudarStatus(['id_pedido' => $p1, 'status' => 'entregue'], $MASTER);
check($r['sucesso'] && $r['dados']['status'] === 'entregue', 'balcão: pronto → entregue');
check(!PedidoController::mudarStatus(['id_pedido' => $p1, 'status' => 'cancelado'], $MASTER)['sucesso'], 'pedido entregue não pode ser cancelado');
check($estoque(1) == $antesPao - 2, 'estoque não é baixado duas vezes');

// cancelamento devolve estoque
$r = PedidoController::criarManual(['canal' => 'balcao', 'itens' => [['id_produto' => 7, 'quantidade' => 1]]], $MASTER); $p2 = $r['dados']['id_pedido'];
$batata = $estoque(7);
PedidoController::mudarStatus(['id_pedido' => $p2, 'status' => 'em preparo'], $MASTER);
check(abs($estoque(7) - ($batata - 0.3)) < 1e-9, 'baixa de batata (0,3 kg)');
$r = PedidoController::mudarStatus(['id_pedido' => $p2, 'status' => 'cancelado'], $MASTER);
check($r['sucesso'] && abs($estoque(7) - $batata) < 1e-9, 'cancelar após preparo estorna o estoque');

// falta de estoque: não confirma e não deixa meio-caminho
$q('UPDATE insumo SET estoque_atual = 0.1 WHERE id_insumo = 4'); // bacon
$r = PedidoController::criarManual(['canal' => 'balcao', 'itens' => [['id_produto' => 2, 'quantidade' => 3]]], $MASTER); $p3 = $r['dados']['id_pedido'];
$pao = $estoque(1);
$r = PedidoController::mudarStatus(['id_pedido' => $p3, 'status' => 'em preparo'], $MASTER);
check(!$r['sucesso'] && str_contains(erroDe($r), 'Estoque insuficiente'), 'sem estoque: confirmação recusada com mensagem clara');
check($q('SELECT status FROM pedido WHERE id_pedido = ?', [$p3])->fetchColumn() === 'novo' && $estoque(1) == $pao, 'transação: status e estoque intactos após a falha (rollback)');
$q('UPDATE insumo SET estoque_atual = 4 WHERE id_insumo = 4');
PedidoController::mudarStatus(['id_pedido' => $p3, 'status' => 'cancelado'], $MASTER);

echo "== RF19 delivery próprio (cliente) ==" . PHP_EOL;
// o cliente tenta manipular preço/canal/cliente: tudo é ignorado
$r = PedidoController::criarDoCliente([
    'itens' => [['id_produto' => 5, 'quantidade' => 1, 'preco_unitario' => 0.01]], 'forma_pagamento' => 'pix',
    'canal' => 'balcao', 'id_usuario_cliente' => $MASTER, 'valor_total' => 1,
], $CLIENTE);
check($r['sucesso'] && $r['dados']['canal'] === 'delivery_proprio' && $r['dados']['valor_total'] == 38.9, 'preço e canal vêm do servidor (38,90 / delivery próprio)'); $pc = $r['dados']['id_pedido'];
check((int) $q('SELECT id_usuario_cliente FROM pedido WHERE id_pedido = ?', [$pc])->fetchColumn() === $CLIENTE, 'pedido vinculado ao cliente autenticado');
check($r['dados']['quitado'] && $r['dados']['valor_pago'] == 38.9, 'forma de pagamento escolhida registrada (RF22)');
check(str_contains((string) $r['dados']['endereco_entrega'], 'Rua das Flores'), 'endereço padrão = cadastro do cliente');
check(!PedidoController::criarDoCliente(['itens' => [['id_produto' => 5, 'quantidade' => 1]]], $CLIENTE)['sucesso'], 'exige forma de pagamento');
$mine = PedidoController::doCliente($CLIENTE);
check($mine['sucesso'] && count($mine['dados']) === 1 && $mine['dados'][0]['id_pedido'] === $pc, 'cliente vê só os próprios pedidos');

echo "== RF21 entregadores e entrega ==" . PHP_EOL;
check(!PedidoController::mudarStatus(['id_pedido' => $pc, 'status' => 'em preparo'], $MASTER)['sucesso'] === false, 'delivery vai para preparo');
PedidoController::mudarStatus(['id_pedido' => $pc, 'status' => 'pronto'], $MASTER);
check(!PedidoController::mudarStatus(['id_pedido' => $pc, 'status' => 'entregue'], $MASTER)['sucesso'], 'delivery não vai direto de pronto a entregue');
check(!PedidoController::mudarStatus(['id_pedido' => $pc, 'status' => 'saiu'], $MASTER)['sucesso'], 'sair para entrega exige entregador');
$r = PedidoController::mudarStatus(['id_pedido' => $pc, 'status' => 'saiu', 'id_entregador' => 1], $MASTER);
check($r['sucesso'] && $r['dados']['entregador'] === 'Carlos Henrique', 'atribui entregador e sai para entrega');
check($q('SELECT status FROM entregador WHERE id_entregador = 1')->fetchColumn() === 'em_rota', 'entregador fica "em rota"');
$r = PedidoController::criarManual(['canal' => 'delivery_proprio', 'itens' => [['id_produto' => 9, 'quantidade' => 1]], 'endereco_entrega' => 'Av. Brasil, 500, Centro', 'telefone_cliente' => '(+55)21-977776666'], $MASTER); $pd2 = $r['dados']['id_pedido'];
check(!PedidoController::atribuirEntregador(['id_pedido' => $pd2, 'id_entregador' => 1])['sucesso'], 'entregador em rota não recebe outra entrega');
check(!PedidoController::atribuirEntregador(['id_pedido' => $p1, 'id_entregador' => 2])['sucesso'], 'entregador não se aplica a pedido de balcão');
check(PedidoController::atribuirEntregador(['id_pedido' => $pd2, 'id_entregador' => 2])['sucesso'], 'atribui outro entregador a pedido novo');
check(!PedidoController::excluirEntregador(['id_entregador' => 1])['sucesso'], 'não exclui entregador com histórico');
$r = PedidoController::mudarStatus(['id_pedido' => $pc, 'status' => 'entregue'], $MASTER);
check($r['sucesso'] && $q('SELECT status FROM entregador WHERE id_entregador = 1')->fetchColumn() === 'disponivel', 'entregue libera o entregador');
$r = PedidoController::salvarEntregador(['nome' => 'Paulo Roberto', 'telefone' => '(+55)21-944443333', 'veiculo' => 'Moto', 'status' => 'disponivel']);
check($r['sucesso'], 'cadastra entregador'); check(!PedidoController::salvarEntregador(['nome' => 'Paulo', 'telefone' => '123'])['sucesso'], 'valida telefone do entregador');
PedidoController::excluirEntregador(['id_entregador' => $r['dados']['id_entregador']]);
PedidoController::mudarStatus(['id_pedido' => $pd2, 'status' => 'cancelado'], $MASTER);

echo "== Mesas, conta e divisão (RF22) ==" . PHP_EOL;
$r = PedidoController::criarManual(['canal' => 'mesa', 'id_mesa' => 3, 'itens' => [['id_produto' => 3, 'quantidade' => 1], ['id_produto' => 6, 'quantidade' => 1]]], $MASTER); $pm = $r['dados']['id_pedido'];
// 34,90 + 47,90 = 82,80
$m3 = array_values(array_filter(PedidoController::mesas()['dados'], fn($m) => $m['numero'] === 3))[0];
check($m3['status'] === 'ocupada' && $m3['saldo'] == 82.8, 'mesa fica ocupada com saldo 82,80');
check(!PedidoController::excluirMesa(['id_mesa' => 3])['sucesso'], 'não exclui mesa ocupada');
PedidoController::mudarStatus(['id_pedido' => $pm, 'status' => 'em preparo'], $MASTER);
PedidoController::mudarStatus(['id_pedido' => $pm, 'status' => 'pronto'], $MASTER);
$r = PedidoController::fecharContaMesa(['id_mesa' => 3, 'pagamentos' => [['forma' => 'pix', 'valor' => 50], ['forma' => 'credito', 'valor' => '40']]], $MASTER);
check(!$r['sucesso'], 'recusa total acima do saldo (90 > 82,80)');
check((int) $q('SELECT COUNT(*) FROM pagamento WHERE id_pedido = ?', [$pm])->fetchColumn() === 0, 'nada gravado quando a divisão é recusada');
$r = PedidoController::fecharContaMesa(['id_mesa' => 3, 'pagamentos' => [['forma' => 'pix', 'valor' => 50], ['forma' => 'credito', 'valor' => '32,80']]], $MASTER);
check($r['sucesso'] && $r['dados']['status'] === 'livre' && $r['dados']['saldo'] == 0, 'conta dividida em 2 formas quita e libera a mesa');
check($q('SELECT status FROM pedido WHERE id_pedido = ?', [$pm])->fetchColumn() === 'entregue', 'pedido pronto e quitado vira entregue');

echo "== RF20 delivery integrado (webhook) ==" . PHP_EOL;
$payload = ['plataforma' => 'ifood', 'id_pedido_externo' => 'IF-1001', 'cliente' => ['nome' => 'Ana Souza', 'telefone' => '(+55)21-988887777'],
            'endereco' => 'Rua das Flores, 100, Centro, Rio', 'itens' => [['nome' => 'x-burger clássico', 'quantidade' => 2], ['id_produto' => 9, 'quantidade' => 1]]];
$r = PedidoController::receberWebhook($payload);
check($r['sucesso'] && $r['dados']['canal'] === 'ifood' && $r['dados']['valor_total'] == 56.3 && $r['dados']['quitado'], 'webhook cria pedido iFood (56,30, pago)'); $pe = $r['dados']['id_pedido'];
$r2 = PedidoController::receberWebhook($payload);
check($r2['sucesso'] && $r2['dados']['duplicado'] === true && $r2['dados']['id_pedido'] === $pe, 'reenvio do mesmo id externo é idempotente');
check((int) $q("SELECT COUNT(*) FROM pedido WHERE id_pedido_externo = 'IF-1001'")->fetchColumn() === 1, 'um único pedido gravado');
check(!PedidoController::receberWebhook(['plataforma' => 'uber', 'id_pedido_externo' => 'X', 'itens' => []])['sucesso'], 'plataforma desconhecida é recusada');
check(!PedidoController::receberWebhook(['plataforma' => 'ifood', 'id_pedido_externo' => 'IF-1002', 'cliente' => ['telefone' => '(+55)21-988887777'], 'endereco' => 'Rua das Flores, 100, Centro', 'itens' => [['nome' => 'Produto Fantasma', 'quantidade' => 1]]])['sucesso'], 'item desconhecido é recusado');
PedidoController::mudarStatus(['id_pedido' => $pe, 'status' => 'em preparo'], $MASTER);
check((int) $q('SELECT sincronizado_status_externo FROM pedido WHERE id_pedido = ?', [$pe])->fetchColumn() === 1, 'status sincronizado com a plataforma (adapter simulado)');

echo "== Atraso no preparo ==" . PHP_EOL;
$q("UPDATE pedido SET data_hora_inicio_preparo = DATE_SUB(NOW(), INTERVAL 45 MINUTE) WHERE id_pedido = ?", [$pe]);
$r = PedidoController::cozinha(); $ped = array_values(array_filter($r['dados']['pedidos'], fn($p) => $p['id_pedido'] === $pe))[0] ?? null;
check($ped && $ped['atrasado'] && $ped['minutos_em_preparo'] >= 44, 'pedido em preparo há 45 min é marcado como atrasado');
PedidoController::quadro([]); PedidoController::cozinha();
check((int) $q("SELECT COUNT(*) FROM notificacao_cliente WHERE id_pedido = ? AND tipo = 'atraso'", [$pe])->fetchColumn() === 1, 'aviso de atraso enviado uma única vez');
$qd = PedidoController::quadro([]);
check($qd['sucesso'] && count($qd['dados']['colunas']['em preparo']) >= 1 && isset($qd['dados']['colunas']['saiu']), 'quadro kanban agrupa por status');

echo "== Integração com Financeiro (Lucas) ==" . PHP_EOL;
$hoje = PeriodoService::hoje();
$f = FechamentoCaixaController::consultar(['data' => $hoje]);
check($f['sucesso'] && $f['dados']['total_geral'] > 0, 'fechamento de caixa enxerga os pagamentos dos pedidos novos');
$dash = DashboardController::indicadores();
check($dash['sucesso'] && $dash['dados']['indicadores']['vendas_hoje']['pedidos'] >= 4, 'dashboard soma os pedidos do dia');

echo "== Contas a pagar / a receber ==" . PHP_EOL;
$q("DELETE FROM conta_financeira");   // parte de um estado limpo; o repasse volta pela autocorreção
$r = PedidoController::receberWebhook(['plataforma' => 'ifood', 'id_pedido_externo' => 'IF-2001', 'cliente' => ['nome' => 'Bia', 'telefone' => '(+55)21-988887777'],
    'endereco' => 'Rua das Flores, 100, Centro', 'itens' => [['id_produto' => 1, 'quantidade' => 2]]]);
$pr = $r['dados']['id_pedido'];
$c = $q('SELECT * FROM conta_financeira WHERE id_pedido = ?', [$pr])->fetch();
check($c && $c['tipo'] === 'receber' && $c['categoria'] === 'repasse_plataforma', 'pedido do iFood gera conta a receber automaticamente');
check($c && (float) $c['valor_bruto'] == 49.80 && (float) $c['valor'] == 43.82 && (float) $c['comissao_percentual'] == 12.0, 'repasse = 49,80 − 12% = 43,82');
check($c && $c['data_vencimento'] === (new DateTimeImmutable($hoje))->modify('+30 days')->format('Y-m-d'), 'vencimento = hoje + prazo de 30 dias do iFood');
PedidoController::receberWebhook(['plataforma' => 'ifood', 'id_pedido_externo' => 'IF-2001', 'cliente' => ['telefone' => '(+55)21-988887777'], 'endereco' => 'Rua das Flores, 100, Centro', 'itens' => [['id_produto' => 1, 'quantidade' => 2]]]);
check((int) $q('SELECT COUNT(*) FROM conta_financeira WHERE id_pedido = ?', [$pr])->fetchColumn() === 1, 'reenvio do webhook não duplica o repasse');
$lst = ContaController::listar(['tipo' => 'receber', 'situacao' => 'aberta']);
check($lst['sucesso'] && count($lst['dados']['contas']) >= 1 && $lst['dados']['contas'][0]['automatica'], 'lista contas a receber em aberto');
// o pedido antigo IF-1001 (criado antes do módulo) ganha repasse pela autocorreção
check((int) $q("SELECT COUNT(*) FROM conta_financeira c JOIN pedido p ON p.id_pedido = c.id_pedido WHERE p.id_pedido_externo = 'IF-1001'")->fetchColumn() === 1, 'autocorreção gera o repasse de pedidos externos que estavam sem conta');
$idc = (int) $c['id_conta'];

// vencida
check(ContaController::listar(['tipo' => 'receber', 'situacao' => 'vencida'])['dados']['contas'] === [], 'nenhuma conta vencida enquanto o prazo não passa');
$q("UPDATE conta_financeira SET data_vencimento = DATE_SUB(?, INTERVAL 3 DAY) WHERE id_conta = ?", [$hoje, $idc]);
$v = ContaController::listar(['tipo' => 'receber', 'situacao' => 'vencida']);
check($v['sucesso'] && count($v['dados']['contas']) === 1 && $v['dados']['contas'][0]['situacao'] === 'vencida' && $v['dados']['contas'][0]['dias_atraso'] === 3 && $v['dados']['resumo']['qtd_vencidas'] === 1, 'conta aberta com vencimento passado = vencida (3 dias de atraso)');

// baixa
$amanha = (new DateTimeImmutable($hoje))->modify('+1 day')->format('Y-m-d');
check(!ContaController::baixar(['id_conta' => $idc, 'data_baixa' => $amanha], $MASTER)['sucesso'], 'baixa no futuro é recusada');
check(!ContaController::baixar(['id_conta' => $idc, 'valor' => '0'], $MASTER)['sucesso'], 'baixa com valor zero é recusada');
$r = ContaController::baixar(['id_conta' => $idc, 'valor' => '43,00'], $MASTER);
check($r['sucesso'] && $r['dados']['status'] === 'baixada' && $r['dados']['valor'] == 43.0 && $r['dados']['data_baixa'] === $hoje, 'baixa com o valor realmente recebido (43,00)');
check(!ContaController::baixar(['id_conta' => $idc], $MASTER)['sucesso'], 'não baixa duas vezes');
check(!ContaController::cancelar(['id_conta' => $idc])['sucesso'], 'não cancela conta já baixada');
check(ContaController::estornar(['id_conta' => $idc])['dados']['status'] === 'aberta', 'estorno reabre a conta');
check(!ContaController::estornar(['id_conta' => $idc])['sucesso'], 'só estorna conta baixada');
check(!ContaController::salvar(['id_conta' => $idc, 'tipo' => 'receber', 'descricao' => 'x', 'categoria' => 'outros', 'valor' => 5, 'data_vencimento' => $hoje])['sucesso'], 'repasse automático não é editável');

// cancelamento do pedido cancela o repasse
$r = PedidoController::receberWebhook(['plataforma' => 'ifood', 'id_pedido_externo' => 'IF-2002', 'cliente' => ['telefone' => '(+55)21-988887777'], 'endereco' => 'Rua das Flores, 100, Centro', 'itens' => [['id_produto' => 9, 'quantidade' => 1]]]);
$pc = $r['dados']['id_pedido'];
PedidoController::mudarStatus(['id_pedido' => $pc, 'status' => 'cancelado'], $MASTER);
check($q('SELECT status FROM conta_financeira WHERE id_pedido = ?', [$pc])->fetchColumn() === 'cancelada', 'cancelar o pedido cancela o repasse previsto');

// contas manuais a pagar
$base = ['tipo' => 'pagar', 'descricao' => 'Aluguel de novembro', 'categoria' => 'aluguel', 'pessoa' => 'Imobiliária X', 'valor' => '2.500,00', 'data_vencimento' => $hoje];
$r = ContaController::salvar($base);
check($r['sucesso'] && $r['dados']['valor'] == 2500.0 && $r['dados']['situacao'] === 'aberta', 'cria conta a pagar (valor "2.500,00")'); $idp = $r['dados']['id_conta'];
check(!ContaController::salvar(['valor' => '0'] + $base)['sucesso'], 'rejeita valor zero');
check(!ContaController::salvar(['categoria' => 'repasse_plataforma'] + $base)['sucesso'], 'rejeita categoria de outro tipo');
check(!ContaController::salvar(['data_vencimento' => ''] + $base)['sucesso'], 'rejeita vencimento vazio');
check(!ContaController::salvar(['data_vencimento' => '2026-02-31'] + $base)['sucesso'], 'rejeita data inexistente');
check(!ContaController::salvar(['descricao' => ''] + $base)['sucesso'], 'rejeita descrição vazia');
check(!ContaController::listar(['tipo' => 'xxx'])['sucesso'], 'tipo de lista inválido é recusado');
check(ContaController::salvar(['id_conta' => $idp, 'valor' => '2400'] + $base)['dados']['valor'] == 2400.0, 'edita conta em aberto');
$r2 = ContaController::salvar(['descricao' => 'Energia', 'categoria' => 'energia', 'valor' => '300', 'data_vencimento' => $hoje] + $base); $idp2 = $r2['dados']['id_conta'];
$r = ContaController::baixar(['id_conta' => $idp], $MASTER);
check($r['sucesso'] && $r['dados']['status'] === 'baixada', 'baixa conta a pagar');
check(!ContaController::salvar(['id_conta' => $idp] + $base)['sucesso'], 'conta baixada não é editável');
// lote: tudo ou nada
$lote = ContaController::baixarLote(['ids' => [$idp2, $idp]], $MASTER);
check(!$lote['sucesso'] && $q('SELECT status FROM conta_financeira WHERE id_conta = ?', [$idp2])->fetchColumn() === 'aberta', 'lote com uma conta inválida não baixa nenhuma (tudo ou nada)');
check(ContaController::baixarLote(['ids' => [$idp2]], $MASTER)['dados']['baixadas'] === 1, 'baixa em lote');

// fluxo de caixa
$fx = ContaController::fluxo(['de' => $hoje, 'ate' => $hoje])['dados'];
$vendasDir = (float) $q("SELECT COALESCE(SUM(pg.valor),0) FROM pagamento pg JOIN pedido p ON p.id_pedido = pg.id_pedido JOIN canal_venda cv ON cv.codigo = p.canal WHERE cv.externo = 0 AND p.status <> 'cancelado' AND DATE(pg.data_hora) = ?", [$hoje])->fetchColumn();
check(abs($fx['entradas_vendas_diretas'] - $vendasDir) < 0.01, 'fluxo: entradas de vendas diretas (sem plataformas) conferem com os pagamentos');
check($fx['saidas_pagas'] == 2700.0, 'fluxo: saídas pagas = 2.400 + 300');
check(abs($fx['saldo'] - ($fx['entradas_vendas_diretas'] + $fx['entradas_recebimentos'] - $fx['saidas_pagas'])) < 0.01, 'fluxo: saldo = entradas − saídas');
check($fx['a_receber'] > 0 && $fx['a_pagar'] == 0.0, 'fluxo: a receber em aberto e nada a pagar');
check(!ContaController::fluxo(['de' => $amanha, 'ate' => $hoje])['sucesso'], 'fluxo rejeita período invertido');

// canais
$cn = ContaController::canais();
check($cn['sucesso'] && count($cn['dados']) === 2, 'lista comissão/prazo dos canais externos (iFood e Rappi)');
check(!ContaController::salvarCanal(['codigo' => 'ifood', 'comissao_percentual' => '120', 'prazo_repasse_dias' => 30])['sucesso'], 'rejeita comissão acima de 100%');
check(!ContaController::salvarCanal(['codigo' => 'balcao', 'comissao_percentual' => '5', 'prazo_repasse_dias' => 1])['sucesso'] || (float) $q("SELECT comissao_percentual FROM canal_venda WHERE codigo = 'balcao'")->fetchColumn() == 0.0, 'canal próprio não recebe comissão');
check(ContaController::salvarCanal(['codigo' => 'rappi', 'comissao_percentual' => '20', 'prazo_repasse_dias' => 7])['sucesso'], 'altera comissão/prazo do Rappi');
$r = PedidoController::receberWebhook(['plataforma' => 'rappi', 'id_pedido_externo' => 'RP-1', 'cliente' => ['telefone' => '(+55)21-988887777'], 'endereco' => 'Rua das Flores, 100, Centro', 'itens' => [['id_produto' => 1, 'quantidade' => 2]]]);
$cr = $q('SELECT * FROM conta_financeira WHERE id_pedido = ?', [$r['dados']['id_pedido']])->fetch();
check($cr && (float) $cr['valor'] == 39.84 && $cr['data_vencimento'] === (new DateTimeImmutable($hoje))->modify('+7 days')->format('Y-m-d'), 'novo pedido usa a comissão (20%) e o prazo (7 dias) configurados');

// compra de insumo gera conta a pagar
$antes = $estoque(5);
$r = InsumoController::movimentar(['id_insumo' => 5, 'tipo' => 'entrada', 'quantidade' => '10', 'motivo' => 'compra',
    'gerar_conta' => ['ativo' => true, 'pessoa' => 'Frigorífico Boi Bom', 'valor' => '120,00', 'data_vencimento' => $amanha]], $MASTER);
check($r['sucesso'] && !empty($r['dados']['id_conta']) && abs($estoque(5) - ($antes + 10)) < 0.001, 'entrada de estoque gera a conta a pagar da compra');
$cc = $q('SELECT * FROM conta_financeira WHERE id_conta = ?', [$r['dados']['id_conta'] ?? 0])->fetch();
check($cc && $cc['categoria'] === 'insumos' && $cc['pessoa'] === 'Frigorífico Boi Bom' && (float) $cc['valor'] == 120.0, 'conta da compra: categoria insumos, fornecedor e valor corretos');
$antes = $estoque(5);
$r = InsumoController::movimentar(['id_insumo' => 5, 'tipo' => 'entrada', 'quantidade' => '10', 'gerar_conta' => ['ativo' => true, 'valor' => '120,00']], $MASTER);
check(!$r['sucesso'] && abs($estoque(5) - $antes) < 0.001, 'conta inválida (sem vencimento) impede a entrada: estoque não muda');

// o caixa não é afetado pelas contas
$caixaTotal = FechamentoCaixaController::consultar(['data' => $hoje])['dados']['total_geral'];
$somaPag = (float) $q("SELECT COALESCE(SUM(pg.valor),0) FROM pagamento pg JOIN pedido p ON p.id_pedido = pg.id_pedido WHERE p.status <> 'cancelado' AND DATE(pg.data_hora) = ?", [$hoje])->fetchColumn();
check(abs($caixaTotal - $somaPag) < 0.01, 'fechamento de caixa continua = soma dos pagamentos dos pedidos (contas não entram)');
$dash = DashboardController::indicadores();
check($dash['sucesso'] && isset($dash['dados']['indicadores']['financeiro']['saldo_mes']) && isset($dash['dados']['indicadores']['financeiro']['pagar_vencidas']), 'dashboard traz o resumo financeiro (saldo do mês e vencidas)');

echo "== Setores de preparo (Cozinha / Bar / Sem preparo) ==" . PHP_EOL;
$setorDe = fn(int $idProd) => $q('SELECT setor_preparo FROM produto WHERE id_produto = ?', [$idProd])->fetchColumn();
check($setorDe(1) === 'cozinha' && $setorDe(10) === 'bar' && $setorDe(11) === 'nenhum' && $setorDe(9) === 'nenhum', 'cardápio de exemplo: lanche na cozinha, suco no bar, água/lata sem preparo');
$r = ProdutoController::salvar(['nome' => 'Caipirinha', 'id_categoria' => 4, 'preco_venda' => '18,00', 'ativo' => 1, 'setor_preparo' => 'bar']);
check($r['sucesso'] && $setorDe($r['dados']['id_produto']) === 'bar', 'cadastra produto com setor Bar');
$idCaip = $r['dados']['id_produto'];
check(!ProdutoController::salvar(['nome' => 'Xpto', 'id_categoria' => 4, 'preco_venda' => '5', 'setor_preparo' => 'garagem'])['sucesso'], 'setor de preparo inválido é recusado');
$lp = array_values(array_filter(ProdutoController::listar([])['dados'], fn($p) => $p['id_produto'] === $idCaip))[0] ?? null;
check($lp && $lp['setor_preparo'] === 'bar' && $lp['setor_rotulo'] === 'Bar', 'listagem de produtos traz o setor');
$noPainel = fn(string $setor, int $idPed) => array_values(array_filter(PedidoController::cozinha($setor)['dados']['pedidos'], fn($p) => $p['id_pedido'] === $idPed))[0] ?? null;
$stItem = fn(int $idPed, int $idProd) => $q('SELECT status_item FROM item_pedido WHERE id_pedido = ? AND id_produto = ?', [$idPed, $idProd])->fetchColumn();

// 1) mesa com só água: vai direto para a mesa
$agua = $estoque(16);
$r = PedidoController::criarManual(['canal' => 'mesa', 'id_mesa' => 7, 'itens' => [['id_produto' => 11, 'quantidade' => 2]]], $MASTER); $pa = $r['dados']['id_pedido'];
check($r['sucesso'] && $r['dados']['status'] === 'entregue', 'mesa com só água: pedido já nasce entregue');
check($stItem($pa, 11) === 'entregue' && abs($estoque(16) - ($agua - 2)) < 0.001, 'item servido e estoque da água baixado na hora');
check($noPainel('cozinha', $pa) === null && $noPainel('bar', $pa) === null, 'não aparece na cozinha nem no bar');
$m7 = array_values(array_filter(PedidoController::mesas()['dados'], fn($m) => $m['numero'] === 7))[0];
check($m7['status'] === 'ocupada' && $m7['saldo'] == 9.0, 'mesa continua ocupada com a conta da água (9,00) até pagar');

// 2) delivery com só refrigerante: pronto, sai com o entregador
$r = PedidoController::criarManual(['canal' => 'delivery_proprio', 'itens' => [['id_produto' => 9, 'quantidade' => 1]], 'endereco_entrega' => 'Av. Brasil, 500, Centro', 'telefone_cliente' => '(+55)21-977776666'], $MASTER); $pdl = $r['dados']['id_pedido'];
check($r['sucesso'] && $r['dados']['status'] === 'pronto' && $stItem($pdl, 9) === 'pronto', 'delivery com só lata: nasce pronto (vai na sacola, não "entregue")');
check(PedidoController::mudarStatus(['id_pedido' => $pdl, 'status' => 'saiu', 'id_entregador' => 3], $MASTER)['sucesso'], 'delivery sem preparo sai para entrega normalmente');
PedidoController::mudarStatus(['id_pedido' => $pdl, 'status' => 'entregue'], $MASTER);

// 3) mesa mista: lanche (cozinha) + suco (bar) + água (sem preparo)
$pao = $estoque(1); $polpa = $estoque(12);
$r = PedidoController::criarManual(['canal' => 'mesa', 'id_mesa' => 7, 'itens' => [['id_produto' => 1, 'quantidade' => 1], ['id_produto' => 10, 'quantidade' => 1], ['id_produto' => 11, 'quantidade' => 1]]], $MASTER); $pmx = $r['dados']['id_pedido'];
check($r['sucesso'] && $r['dados']['status'] === 'novo' && $stItem($pmx, 11) === 'entregue', 'pedido misto: entra como novo e a água já é servida');
$tc = $noPainel('cozinha', $pmx); $tb = $noPainel('bar', $pmx);
check($tc && count($tc['itens']) === 1 && $tc['itens'][0]['id_produto'] === 1 && $tc['estado_setor'] === 'aguardando', 'cozinha vê só o X-Burger');
check($tb && count($tb['itens']) === 1 && $tb['itens'][0]['id_produto'] === 10, 'bar vê só o suco');
check($tc && $tc['outros_setores'][0]['setor'] === 'bar' && $tc['outros_setores'][0]['estado'] === 'aguardando', 'cozinha enxerga o andamento do bar');
check(!PedidoController::avancarSetor(['id_pedido' => $pmx, 'setor' => 'bar', 'etapa' => 'pronto'], $MASTER)['sucesso'], 'bar não marca pronto sem iniciar');
check(!PedidoController::avancarSetor(['id_pedido' => $pmx, 'setor' => 'nenhum', 'etapa' => 'iniciar'], $MASTER)['sucesso'], 'setor sem preparo não tem tela');
$r = PedidoController::avancarSetor(['id_pedido' => $pmx, 'setor' => 'cozinha', 'etapa' => 'iniciar'], $MASTER);
check($r['sucesso'] && $r['dados']['status'] === 'em preparo', 'cozinha inicia: pedido vai para "em preparo"');
check(abs($estoque(1) - ($pao - 1)) < 0.001 && abs($estoque(12) - ($polpa - 0.12)) < 0.001, 'estoque do pedido inteiro baixado no 1º início');
check($stItem($pmx, 1) === 'em preparo' && $stItem($pmx, 10) === 'aguardando', 'só o lanche entra em preparo; o suco segue aguardando');
check(($noPainel('bar', $pmx)['estado_setor'] ?? '') === 'aguardando', 'bar ainda mostra "Iniciar preparo"');
$r = PedidoController::avancarSetor(['id_pedido' => $pmx, 'setor' => 'cozinha', 'etapa' => 'pronto'], $MASTER);
check($r['sucesso'] && $r['dados']['status'] === 'em preparo' && $noPainel('cozinha', $pmx) === null, 'cozinha termina: some da cozinha, pedido espera o bar');
PedidoController::avancarSetor(['id_pedido' => $pmx, 'setor' => 'bar', 'etapa' => 'iniciar'], $MASTER);
$r = PedidoController::avancarSetor(['id_pedido' => $pmx, 'setor' => 'bar', 'etapa' => 'pronto'], $MASTER);
check($r['sucesso'] && $r['dados']['status'] === 'pronto' && $noPainel('bar', $pmx) === null, 'último setor termina: pedido completo fica "pronto"');
$r = PedidoController::criarManual(['canal' => 'delivery_proprio', 'itens' => [['id_produto' => 2, 'quantidade' => 1], ['id_produto' => 10, 'quantidade' => 1], ['id_produto' => 9, 'quantidade' => 1]],
    'endereco_entrega' => 'Av. Brasil, 500, Centro', 'telefone_cliente' => '(+55)21-977776666'], $MASTER); $pdm = $r['dados']['id_pedido'];
$tc = $noPainel('cozinha', $pdm);
check($stItem($pdm, 9) === 'pronto' && $tc && count($tc['sem_preparo']) === 1, 'delivery misto: lata fica "pronta" e a cozinha vê que ela vai junto');
foreach (['cozinha', 'bar'] as $s) { foreach (['iniciar', 'pronto'] as $et) { PedidoController::avancarSetor(['id_pedido' => $pdm, 'setor' => $s, 'etapa' => $et], $MASTER); } }
check($q('SELECT status FROM pedido WHERE id_pedido = ?', [$pdm])->fetchColumn() === 'pronto'
    && (int) $q("SELECT COUNT(*) FROM notificacao_cliente WHERE id_pedido = ? AND tipo = 'pronto'", [$pdm])->fetchColumn() === 1, 'delivery pronto após os dois setores: cliente avisado uma vez');
check(!PedidoController::avancarSetor(['id_pedido' => $pmx, 'setor' => 'bar', 'etapa' => 'iniciar'], $MASTER)['sucesso'], 'pedido pronto não volta para a fila');
$r = PedidoController::fecharContaMesa(['id_mesa' => 7, 'pagamentos' => [['forma' => 'pix', 'valor' => 9.0 + 24.90 + 9.90 + 4.50]]], $MASTER);
check($r['sucesso'] && $r['dados']['status'] === 'livre' && $stItem($pmx, 1) === 'entregue' && $stItem($pmx, 10) === 'entregue', 'fechar a conta entrega tudo e libera a mesa');

// 4) movido pelo quadro de Pedidos: itens acompanham
$r = PedidoController::criarManual(['canal' => 'balcao', 'itens' => [['id_produto' => 7, 'quantidade' => 1], ['id_produto' => $idCaip, 'quantidade' => 1]]], $MASTER); $pk = $r['dados']['id_pedido'];
PedidoController::mudarStatus(['id_pedido' => $pk, 'status' => 'em preparo'], $MASTER);
check(($noPainel('cozinha', $pk)['estado_setor'] ?? '') === 'em preparo' && ($noPainel('bar', $pk)['estado_setor'] ?? '') === 'em preparo', 'quadro → em preparo: cozinha e bar mostram "Marcar pronto"');
PedidoController::mudarStatus(['id_pedido' => $pk, 'status' => 'pronto'], $MASTER);
check($noPainel('cozinha', $pk) === null && $noPainel('bar', $pk) === null && $stItem($pk, 7) === 'pronto', 'quadro → pronto: sai das duas telas');

// 5) cancelar depois que só a cozinha começou devolve o estoque inteiro
$batata = $estoque(7);
$r = PedidoController::criarManual(['canal' => 'balcao', 'itens' => [['id_produto' => 7, 'quantidade' => 1], ['id_produto' => 10, 'quantidade' => 1]]], $MASTER); $pcx = $r['dados']['id_pedido'];
PedidoController::avancarSetor(['id_pedido' => $pcx, 'setor' => 'cozinha', 'etapa' => 'iniciar'], $MASTER);
PedidoController::mudarStatus(['id_pedido' => $pcx, 'status' => 'cancelado'], $MASTER);
check(abs($estoque(7) - $batata) < 0.001 && $noPainel('bar', $pcx) === null, 'cancelado: estoque devolvido e some do bar');
$geral = PedidoController::cozinha();
check($geral['sucesso'] && $geral['dados']['setor'] === null, 'visão geral (sem setor) continua disponível para o dashboard');

echo "== Funcionários e permissões ==" . PHP_EOL;
require_once __DIR__ . '/../src/Services/PermissaoService.php';
$comoUsuario = function (int $id, string $perfil) { $_SESSION['usuario_id'] = $id; $_SESSION['usuario_perfil'] = $perfil; PermissaoService::limparCache(); };
check(!PermissaoService::salvar($CLIENTE, 'funcionario', ['pedidos' => ['ver']]) === false, 'cliente pode virar funcionário');
check($q('SELECT perfil FROM usuario WHERE id_usuario = ?', [$CLIENTE])->fetchColumn() === 'funcionario', 'perfil gravado como funcionario');
try { PermissaoService::salvar($MASTER, 'comum', []); $ok = false; } catch (RegraNegocioException $e) { $ok = true; }
check($ok && $q('SELECT perfil FROM usuario WHERE id_usuario = ?', [$MASTER])->fetchColumn() === 'master', 'Master não pode ter perfil/permissões alterados');
try { PermissaoService::salvar($CLIENTE, 'funcionario', ['usuarios' => ['ver']]); $ok = false; } catch (RegraNegocioException $e) { $ok = true; }
check($ok, 'tela exclusiva do Master não pode ser liberada');
try { PermissaoService::salvar($CLIENTE, 'funcionario', ['relatorios' => ['excluir']]); $ok = false; } catch (RegraNegocioException $e) { $ok = true; }
check($ok, 'ação que não existe na tela é recusada');
$r = PermissaoService::salvar($CLIENTE, 'funcionario', Permissao::doModelo('garcom') + ['estoque' => ['alterar']]);
check($r['permissoes']['estoque']['ver'] === true && $r['permissoes']['estoque']['excluir'] === false, 'marcar Alterar libera também o Ver');
$comoUsuario($CLIENTE, 'funcionario');
check(PermissaoService::pode('pedidos', 'incluir') && PermissaoService::pode('mesas', 'alterar'), 'garçom: lança pedido e fecha conta da mesa');
check(!PermissaoService::pode('pedidos', 'excluir') && !PermissaoService::pode('contas') && !PermissaoService::pode('caixa'), 'garçom: não cancela pedido nem vê financeiro');
check(PermissaoService::paginaInicial() === 'pedidos.php', 'página inicial do garçom = Pedidos (sem dashboard)');
check(PermissaoService::podeAlgum(['estoque', 'produtos']) && !PermissaoService::pode('produtos'), 'podeAlgum considera qualquer um dos módulos');
$comoUsuario($MASTER, 'master');
check(PermissaoService::pode('contas', 'excluir') && PermissaoService::pode('qualquer_coisa'), 'Master pode tudo');
$comoUsuario(999, 'comum');
check(!PermissaoService::pode('pedidos') && PermissaoService::paginaInicial() === null, 'cliente não acessa telas de gestão');
$r = PermissaoService::salvar($CLIENTE, 'comum', ['pedidos' => ['ver']]);
check($r['perfil'] === 'comum' && $r['permissoes'] === [], 'voltar a Cliente apaga as permissões');
$func = PermissaoService::funcionarios();
check(is_array($func) && count($func) === 0, 'lista de funcionários atualizada');
unset($_SESSION['usuario_id'], $_SESSION['usuario_perfil']);

echo PHP_EOL . ($falhou ? 'RESULTADO: HÁ FALHAS' : 'RESULTADO: TUDO OK') . PHP_EOL;
$raiz->exec('DROP DATABASE IF EXISTS foodhub_teste');
exit($falhou ? 1 : 0);
