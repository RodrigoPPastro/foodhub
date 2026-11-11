<?php
// RF23 — download do PDF do fechamento de caixa (Master). GET ?data=AAAA-MM-DD
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/ApiGuard.php';
require_once __DIR__ . '/../src/Controllers/FechamentoCaixaController.php';

AuthGuard::requirePermissao('caixa');

$resultado = FechamentoCaixaController::pdf($_GET);

if (!$resultado['sucesso']) {
    // Navegação (não é fetch): devolve à tela com a mensagem em toast.
    SessionService::setFlash('erro_pdf', $resultado['mensagem']);
    header('Location: fechamento_caixa.php');
    exit;
}

ApiGuard::enviarPdf($resultado['dados']['conteudo'], $resultado['dados']['arquivo']);
