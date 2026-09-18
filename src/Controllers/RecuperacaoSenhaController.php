<?php

require_once __DIR__ . '/../Services/RecuperacaoSenhaService.php';
require_once __DIR__ . '/../Services/SessionService.php';

class RecuperacaoSenhaController
{
    /**
     * Inicia a recuperação a partir do login informado.
     */
    public static function iniciar(): void
    {
        SessionService::start();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /foodhub/public/recuperar_senha.php');
            exit;
        }

        if (SessionService::estaAutenticado()) {
            header('Location: /foodhub/public/dashboard.php');
            exit;
        }

        $login = trim((string) ($_POST['login'] ?? ''));

        $resultado = RecuperacaoSenhaService::iniciar($login);

        if ($resultado['status'] === 'pergunta_pendente') {
            header('Location: /foodhub/public/recuperar_senha.php?etapa=pergunta');
            exit;
        }

        // Mensagem genérica para não revelar se o login existe
        // ou se possui recuperação disponível.
        SessionService::setFlash(
            'recuperacao_erro',
            'Não foi possível iniciar a recuperação da senha.'
        );

        header('Location: /foodhub/public/recuperar_senha.php?erro=indisponivel');
        exit;
    }

    /**
     * Valida a resposta da pergunta de segurança.
     */
    public static function validarPergunta(): void
    {
        SessionService::start();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /foodhub/public/recuperar_senha.php');
            exit;
        }

        if (SessionService::estaAutenticado()) {
            header('Location: /foodhub/public/dashboard.php');
            exit;
        }

        $resposta = (string) ($_POST['resposta'] ?? '');

        $resultado = RecuperacaoSenhaService::validarResposta($resposta);

        switch ($resultado['status']) {
            case 'sucesso':
                header('Location: /foodhub/public/redefinir_senha.php');
                exit;

            case 'tentativa_invalida':
                SessionService::setFlash(
                    'recuperacao_erro',
                    'Resposta incorreta. Tentativas restantes: '
                    . $resultado['tentativas_restantes'] . '.'
                );

                header(
                    'Location: /foodhub/public/recuperar_senha.php'
                    . '?etapa=pergunta&erro=invalida'
                );
                exit;

            case 'bloqueado':
                SessionService::setFlash(
                    'recuperacao_erro',
                    'Número máximo de tentativas atingido. Inicie a recuperação novamente.'
                );

                header(
                    'Location: /foodhub/public/recuperar_senha.php?erro=bloqueado'
                );
                exit;

            case 'sem_pendencia':
            default:
                SessionService::setFlash(
                    'recuperacao_erro',
                    'A recuperação não está mais disponível. Inicie novamente.'
                );

                header(
                    'Location: /foodhub/public/recuperar_senha.php?erro=sem_pendencia'
                );
                exit;
        }
    }

    /**
     * Redefine a senha depois da validação da pergunta.
     */
    public static function redefinirSenha(): void
    {
        SessionService::start();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /foodhub/public/redefinir_senha.php');
            exit;
        }

        if (SessionService::estaAutenticado()) {
            header('Location: /foodhub/public/dashboard.php');
            exit;
        }

        $novaSenha = (string) ($_POST['nova_senha'] ?? '');
        $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

        $resultado = RecuperacaoSenhaService::redefinirSenha(
            $novaSenha,
            $confirmarSenha
        );

        if ($resultado['sucesso'] === true) {
            SessionService::setFlash(
                'login_sucesso',
                $resultado['mensagem']
            );

            header('Location: /foodhub/public/login.php?sucesso=senha_redefinida');
            exit;
        }

        SessionService::setFlash(
            'recuperacao_erro',
            $resultado['mensagem']
        );

        header(
            'Location: /foodhub/public/redefinir_senha.php'
            . '?erro=' . urlencode((string) $resultado['status'])
        );
        exit;
    }
}
