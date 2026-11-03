<?php
/**
 * RF22 — registro de pagamento (operação do caixa/balcão, perfil Master).
 *   GET  ?id_pedido=N                                  -> pagamentos e saldo do pedido
 *   POST {"id_pedido":N,"pagamentos":[{"forma":"pix","valor":25.5}, ...]}
 *
 * O Delivery Próprio (cliente comum, RF19) NÃO usa este endpoint: o módulo de
 * Pedidos deve chamar PagamentoService::registrar() diretamente no servidor.
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Controllers/PagamentoController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);
ApiGuard::requireMaster();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ApiGuard::exigirAjaxJson();
    ApiGuard::responder(PagamentoController::registrar(ApiGuard::lerJson()));
}

ApiGuard::responder(PagamentoController::consultar($_GET));
