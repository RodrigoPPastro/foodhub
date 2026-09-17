<?php

require_once __DIR__ . '/../Services/SessionService.php';
require_once __DIR__ . '/../Services/AuthService.php';

class AuthController
{
    public static function login(): void
    {
        SessionService::start();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /foodhub/public/login.php');
            exit;
        }

        $login = trim($_POST['login'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if ($login === '' || $senha === '') {
            header('Location: /foodhub/public/login.php?erro=credenciais');
            exit;
        }

        $resultado = AuthService::autenticar($login, $senha);

        switch ($resultado['status']) {

            case 'credenciais_invalidas':
                header('Location: /foodhub/public/login.php?erro=credenciais');
                exit;

            case '2fa_indisponivel':
                header('Location: /foodhub/public/erro.php?tipo=2fa_ausente');
                exit;

            case '2fa_pendente':
                header('Location: /foodhub/public/2fa.php');
                exit;

            default:
                header('Location: /foodhub/public/erro.php?tipo=interno');
                exit;
        }
    }
}