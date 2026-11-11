<?php
// RF24 — GET /api/relatorios.php?relatorio=vendas|produtos|insumos|ticket|canais&inicio=&fim=&limite=
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Controllers/RelatorioController.php';

ApiGuard::exigirMetodo('GET');
ApiGuard::requirePermissao('relatorios');
ApiGuard::responder(RelatorioController::gerar($_GET));
