<?php
/**
 * RF17–RF22 — pedidos.
 *   GET  ?visao=quadro[&canal]        (Master) kanban
 *   GET  ?visao=cozinha[&setor=cozinha|bar]  (Master) painel de preparo (sem setor = todos os pedidos)
 *   GET  ?visao=meus                  (qualquer logado) pedidos do próprio cliente
 *   GET  ?visao=recentes              (Master)
 *   GET  ?visao=historico[&de&ate&canal&busca]   pedidos entregues/cancelados (quem vê Pedidos)
 *   GET  ?id_pedido=N                 (Master)
 *   POST {"acao":"criar", canal, itens:[{id_produto,quantidade,observacao?}], id_mesa?, nome_cliente?, telefone_cliente?,
 *         endereco_entrega?, observacao?, pagamentos?:[{forma,valor}]}                                 (Master)
 *   POST {"acao":"criar_cliente", itens, forma_pagamento, endereco_entrega?, observacao?}      (cliente comum — RF19)
 *   POST {"acao":"status", id_pedido, status, id_entregador?}                                    (Master)
 *   POST {"acao":"preparo", id_pedido, setor: cozinha|bar, etapa: iniciar|pronto}                (Master)
 *   POST {"acao":"entregador", id_pedido, id_entregador}                                         (Master)
 *   POST {"acao":"pagar", id_pedido, pagamentos:[{forma,valor}]}                                 (Master)
 *   POST {"acao":"fechar_conta", id_mesa, pagamentos:[{forma,valor}]}                            (Master)
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Services/SessionService.php';
require_once __DIR__ . '/../../src/Controllers/PedidoController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $visao = $_GET['visao'] ?? '';
    if ($visao === 'meus') {
        ApiGuard::requireLogin();
        ApiGuard::responder(PedidoController::doCliente((int) SessionService::usuarioIdAtual()));
    }
    if ($visao === 'cozinha') {
        // Tela da Cozinha / do Bar: basta ver o próprio setor. Sem setor = visão geral (quem vê Pedidos).
        $setor = isset($_GET['setor']) && $_GET['setor'] !== '' ? (string) $_GET['setor'] : null;
        ApiGuard::requirePermissao(in_array($setor, ['cozinha', 'bar'], true) ? [$setor, 'pedidos'] : ['pedidos', 'dashboard']);
        ApiGuard::responder(PedidoController::cozinha($setor));
    }
    ApiGuard::requirePermissao('pedidos');
    ApiGuard::responder(match (true) {
        $visao === 'quadro'   => PedidoController::quadro($_GET),
        $visao === 'recentes' => PedidoController::recentes(),
        $visao === 'historico' => PedidoController::historico($_GET),
        !empty($_GET['id_pedido']) => PedidoController::buscar($_GET),
        default => ['http' => 400, 'sucesso' => false, 'mensagem' => 'Consulta inválida.'],
    });
}

ApiGuard::requireLogin();
ApiGuard::exigirAjaxJson();
$entrada = ApiGuard::lerJson();
$acao = $entrada['acao'] ?? '';
$idUsuario = SessionService::usuarioIdAtual();

// Somente o cliente comum pode criar pelo delivery próprio; todo o resto é operação da equipe.
if ($acao === 'criar_cliente') {
    if (SessionService::perfilAtual() !== 'comum') {
        ApiGuard::responder(['http' => 403, 'sucesso' => false, 'mensagem' => 'Use a tela de Pedidos para lançar pedidos da equipe.']);
    }
    ApiGuard::responder(PedidoController::criarDoCliente($entrada, (int) $idUsuario));
}

// Permissão de cada ação (Master passa sempre; funcionário conforme o que o Master liberou)
match ($acao) {
    'criar'        => ApiGuard::requirePermissao('pedidos', 'incluir'),
    'status'       => ApiGuard::requirePermissao('pedidos', ($entrada['status'] ?? '') === 'cancelado' ? 'excluir' : 'alterar'),
    'preparo'      => ApiGuard::requirePermissao(in_array($entrada['setor'] ?? '', ['cozinha', 'bar'], true) ? [$entrada['setor'], 'pedidos'] : 'pedidos', 'alterar'),
    'fechar_conta' => ApiGuard::requirePermissao('mesas', 'alterar'),
    default        => ApiGuard::requirePermissao('pedidos', 'alterar'),   // entregador, pagar
};
ApiGuard::responder(match ($acao) {
    'criar'        => PedidoController::criarManual($entrada, $idUsuario),
    'status'       => PedidoController::mudarStatus($entrada, $idUsuario),
    'preparo'      => PedidoController::avancarSetor($entrada, $idUsuario),
    'entregador'   => PedidoController::atribuirEntregador($entrada),
    'pagar'        => PedidoController::pagar($entrada),
    'fechar_conta' => PedidoController::fecharContaMesa($entrada, $idUsuario),
    default        => ['http' => 400, 'sucesso' => false, 'mensagem' => 'Ação inválida.'],
});
