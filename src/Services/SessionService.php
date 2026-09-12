<?php
/**
 * Controla a sessão do usuário autenticado (RNF06: perfil controlado via sessão).
 * Todo módulo do sistema (estoque, pedidos, financeiro etc.) deve usar esta
 * classe (ou o AuthGuard) para saber quem está logado e qual o perfil — nunca
 * ler/gravar $_SESSION diretamente fora daqui, pra evitar inconsistência.
 */

class SessionService
{
    private const SESSION_TIMEOUT_SEGUNDOS = 1800; // 30 min de inatividade

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        self::checarExpiracaoPorInatividade();
    }

    /**
     * Chamado só depois que o 2FA foi validado com sucesso.
     */
    public static function autenticarUsuario(array $usuario): void
    {
        // Regenera o ID de sessão para evitar session fixation.
        session_regenerate_id(true);

        $_SESSION['usuario_id']     = $usuario['id_usuario'];
        $_SESSION['usuario_nome']   = $usuario['nome_completo'];
        $_SESSION['usuario_login']  = $usuario['login'];
        $_SESSION['usuario_perfil'] = $usuario['perfil']; // 'master' | 'comum'
        $_SESSION['ultimo_acesso']  = time();
    }

    public static function estaAutenticado(): bool
    {
        return isset($_SESSION['usuario_id']);
    }

    public static function perfilAtual(): ?string
    {
        return $_SESSION['usuario_perfil'] ?? null;
    }

    public static function usuarioIdAtual(): ?int
    {
        return $_SESSION['usuario_id'] ?? null;
    }

    public static function nomeUsuarioAtual(): ?string
    {
        return $_SESSION['usuario_login'] ?? null;
    }

    public static function ehMaster(): bool
    {
        return self::perfilAtual() === 'master';
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain']);
        }
        session_destroy();
    }

    private static function checarExpiracaoPorInatividade(): void
    {
        if (isset($_SESSION['ultimo_acesso'])
            && (time() - $_SESSION['ultimo_acesso']) > self::SESSION_TIMEOUT_SEGUNDOS
        ) {
            self::logout();
            return;
        }
        $_SESSION['ultimo_acesso'] = time();
    }

    /**
     * Flash messages: sobrevivem só até o próximo request (ideal pra mostrar
     * erro de formulário depois de um redirect, sem usar alert() nativo).
     */
    public static function setFlash(string $chave, $valor): void
    {
        $_SESSION['flash'][$chave] = $valor;
    }

    public static function getFlash(string $chave)
    {
        $valor = $_SESSION['flash'][$chave] ?? null;
        unset($_SESSION['flash'][$chave]);
        return $valor;
    }
}
