<?php
// RF26 (Desafio Plus) — exporta a lista completa de usuários em PDF (Master).
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/ApiGuard.php';
require_once __DIR__ . '/../src/Controllers/UsuarioExportacaoController.php';

AuthGuard::requireMaster();

$resultado = UsuarioExportacaoController::pdf((string) SessionService::nomeUsuarioAtual());

if (!$resultado['sucesso']) {
    SessionService::setFlash('erro_pdf', $resultado['mensagem']);
    header('Location: consulta_usuario.php');
    exit;
}

ApiGuard::enviarPdf($resultado['dados']['conteudo'], $resultado['dados']['arquivo']);
