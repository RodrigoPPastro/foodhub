<?php
// RF25 — GET /api/dashboard.php (Master)
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Controllers/DashboardController.php';

ApiGuard::exigirMetodo('GET');
ApiGuard::requirePermissao('dashboard');
ApiGuard::responder(DashboardController::indicadores());
