<?php
// RF23 — GET ?data=AAAA-MM-DD (prévia/consulta)  |  POST JSON {data, observacoes} (fecha o caixa)
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Controllers/FechamentoCaixaController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);
ApiGuard::requirePermissao('caixa');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ApiGuard::requirePermissao('caixa', 'incluir');   // fechar o caixa do dia
    ApiGuard::exigirAjaxJson();
    ApiGuard::responder(FechamentoCaixaController::fechar(
        (int) SessionService::usuarioIdAtual(),
        ApiGuard::lerJson()
    ));
}

ApiGuard::responder(FechamentoCaixaController::consultar($_GET));
