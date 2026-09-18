<?php

require_once __DIR__ . '/../src/Controllers/RecuperacaoSenhaController.php';

$acao = $_POST['acao'] ?? '';

switch ($acao) {
    case 'iniciar':
        RecuperacaoSenhaController::iniciar();
        break;

    case 'validar_pergunta':
        RecuperacaoSenhaController::validarPergunta();
        break;

    default:
        header('Location: /foodhub/public/recuperar_senha.php?erro=acao_invalida');
        exit;
}