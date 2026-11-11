<?php
/**
 * Mesas do salão (Master).  GET -> lista com pedidos em aberto e saldo
 *   POST {"acao":"salvar", id_mesa?, numero, capacidade} | {"acao":"excluir", id_mesa}
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Controllers/PedidoController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ApiGuard::requirePermissao('mesas');
    ApiGuard::exigirAjaxJson();
    $entrada = ApiGuard::lerJson();
    $acao = $entrada['acao'] ?? '';
    ApiGuard::requirePermissao('mesas', $acao === 'excluir' ? 'excluir' : (!empty($entrada['id_mesa']) ? 'alterar' : 'incluir'));
    ApiGuard::responder(match ($entrada['acao'] ?? '') {
        'salvar'  => PedidoController::salvarMesa($entrada),
        'excluir' => PedidoController::excluirMesa($entrada),
        default   => ['http' => 400, 'sucesso' => false, 'mensagem' => 'Ação inválida.'],
    });
}
ApiGuard::requirePermissao(['mesas', 'pedidos']);   // o novo pedido de mesa lista as mesas
ApiGuard::responder(PedidoController::mesas());
