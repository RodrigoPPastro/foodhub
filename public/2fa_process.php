<?php

require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Services/TwoFactorService.php';
require_once __DIR__ . '/../src/Services/UsuarioService.php';

SessionService::start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /foodhub/public/2fa.php');
    exit;
}

$resposta = $_POST['resposta'] ?? '';
$resultado = TwoFactorService::validarResposta($resposta);

switch ($resultado['status']) {

    case 'sucesso':
        $usuario = UsuarioService::buscarPorId($resultado['usuario_id']);

        if ($usuario === null) {
            header('Location: /foodhub/public/erro.php?tipo=interno');
            exit;
        }

        SessionService::autenticarUsuario($usuario);

        header('Location: /foodhub/public/dashboard.php');
        exit;

    case 'tentativa_invalida':
        header('Location: /foodhub/public/2fa.php?erro=invalida');
        exit;

    case 'bloqueado':
        header('Location: /foodhub/public/login.php?erro=2fa_bloqueado');
        exit;

    default:
        header('Location: /foodhub/public/login.php');
        exit;
    }