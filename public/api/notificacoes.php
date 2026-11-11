<?php
// Histórico das mensagens (simulação de WhatsApp) — Master. GET -> últimas 30.
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Controllers/PedidoController.php';

ApiGuard::exigirMetodo('GET');
ApiGuard::requireMaster();
ApiGuard::responder(PedidoController::notificacoes());
