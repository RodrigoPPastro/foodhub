<?php
/**
 * RF20 — endpoint SIMULADO de delivery integrado (iFood, Rappi…).
 *
 *   POST /api/pedidos_webhook.php
 *   Cabeçalhos: Content-Type: application/json   X-Webhook-Token: <config/integracoes.php>
 *   Corpo: {
 *     "plataforma": "ifood", "id_pedido_externo": "IF-123456",
 *     "cliente": {"nome": "Ana Souza", "telefone": "(+55)21-988887777"},
 *     "endereco": "Rua das Flores, 100, Centro, Rio de Janeiro",
 *     "itens": [{"nome": "X-Burger Clássico", "quantidade": 2}],   // ou "id_produto"
 *     "forma_pagamento": "pix"                                       // opcional (padrão: pix)
 *   }
 * Os preços SEMPRE vêm do cardápio do FOOD HUB. Reenviar o mesmo id_pedido_externo é seguro (idempotente).
 * Para a integração real, basta trocar este endpoint pelo cliente da API do parceiro (RNF09/RNF11).
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Controllers/PedidoController.php';

ApiGuard::exigirMetodo('POST');

$cfg = require __DIR__ . '/../../config/integracoes.php';
$token = $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '';
if ($token === '' || !hash_equals((string) $cfg['webhook_token'], $token)) {
    ApiGuard::responder(['http' => 401, 'sucesso' => false, 'mensagem' => 'Token de integração inválido.']);
}
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    ApiGuard::responder(['http' => 415, 'sucesso' => false, 'mensagem' => 'Envie o corpo como application/json.']);
}

$r = PedidoController::receberWebhook(ApiGuard::lerJson());
if ($r['sucesso'] && !empty($r['dados']['duplicado'])) {
    $r['http'] = 200;
} elseif ($r['sucesso']) {
    $r['http'] = 201;
}
ApiGuard::responder($r);
