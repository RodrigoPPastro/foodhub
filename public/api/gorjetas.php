<?php
/**
 * Taxa de serviço (10%) dos garçons (Master).
 *   GET  ?de=YYYY-MM-DD&ate=YYYY-MM-DD[&id_garcom=]  -> resumo por garçom + lançamentos
 *   GET  ?garcons=1[&todos=1]                          -> lista de garçons (ativos, ou todos)
 *   POST {"acao":"salvar_garcom", id_garcom?, nome, ativo?}
 *   POST {"acao":"pagar", id_garcom, de, ate}  ou  {"acao":"pagar", ids:[...]}   -> marca como pago
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Services/SessionService.php';
require_once __DIR__ . '/../../src/Controllers/GorjetaController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ApiGuard::exigirAjaxJson();
    $entrada = ApiGuard::lerJson();
    ApiGuard::requirePermissao('caixa', 'alterar');
    $idUsuario = SessionService::usuarioIdAtual();
    ApiGuard::responder(match ($entrada['acao'] ?? '') {
        'salvar_garcom' => GorjetaController::salvarGarcom($entrada),
        'pagar'         => GorjetaController::pagar($entrada, $idUsuario),
        default         => ['http' => 400, 'sucesso' => false, 'mensagem' => 'Ação inválida.'],
    });
}
if (isset($_GET['garcons'])) {
    ApiGuard::requirePermissao(['caixa', 'mesas']);   // a tela de mesas lista os garçons ao fechar a conta
    ApiGuard::responder(GorjetaController::garcons(empty($_GET['todos'])));
}
ApiGuard::requirePermissao('caixa');
ApiGuard::responder(GorjetaController::relatorio($_GET));
