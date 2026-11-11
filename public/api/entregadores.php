<?php
/**
 * RF21 — entregadores (Master).  GET -> lista
 *   POST {"acao":"salvar", id_entregador?, nome, telefone, veiculo, status} | {"acao":"excluir", id_entregador}
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Controllers/PedidoController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ApiGuard::requirePermissao('entregadores');
    ApiGuard::exigirAjaxJson();
    $entrada = ApiGuard::lerJson();
    $acao = $entrada['acao'] ?? '';
    ApiGuard::requirePermissao('entregadores', $acao === 'excluir' ? 'excluir' : (!empty($entrada['id_entregador']) ? 'alterar' : 'incluir'));
    ApiGuard::responder(match ($entrada['acao'] ?? '') {
        'salvar'  => PedidoController::salvarEntregador($entrada),
        'excluir' => PedidoController::excluirEntregador($entrada),
        default   => ['http' => 400, 'sucesso' => false, 'mensagem' => 'Ação inválida.'],
    });
}
ApiGuard::requirePermissao(['entregadores', 'pedidos']   /* o quadro de Pedidos escolhe o entregador */);
ApiGuard::responder(PedidoController::entregadores());
