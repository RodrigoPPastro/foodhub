<?php

require_once __DIR__ . '/SessionService.php';

/**
 * ========================================================================
 *  CONTRATO DE INTEGRAÇÃO — leia antes de usar
 * ========================================================================
 * Toda página protegida do sistema (estoque, pedidos, financeiro, cardápio,
 * dashboard, consulta de usuário, log, etc.) deve começar assim:
 *
 *   require_once __DIR__ . '/../src/Services/AuthGuard.php';
 *   AuthGuard::requireLogin();          // qualquer usuário logado
 *   // ou, para telas exclusivas do master:
 *   AuthGuard::requireMaster();
 *
 * Depois disso, para pegar dados do usuário logado em qualquer lugar:
 *   SessionService::usuarioIdAtual();
 *   SessionService::nomeUsuarioAtual();
 *   SessionService::ehMaster();
 * ========================================================================
 */

class AuthGuard
{
    public static function requireLogin(): void
    {
        SessionService::start();

        if (!SessionService::estaAutenticado()) {
            header('Location: /foodhub/public/login.php');
            exit;
        }
    }

    public static function requireMaster(): void
    {
        self::requireLogin();

        if (!SessionService::ehMaster()) {
            header('Location: /foodhub/public/erro.php?tipo=acesso_negado');
            exit;
        }
    }

    public static function requireComum(): void
    {
        self::requireLogin();

        if (SessionService::ehMaster()) {
            header('Location: /foodhub/public/erro.php?tipo=acesso_negado');
            exit;
        }
    }

    /** Somente o cliente do delivery (perfil comum) — ex.: "Meus pedidos". */
    public static function requireCliente(): void
    {
        self::requireLogin();

        if (SessionService::perfilAtual() !== 'comum') {
            header('Location: /foodhub/public/erro.php?tipo=acesso_negado');
            exit;
        }
    }

    /**
     * Tela liberada para o Master e para o funcionário que tiver a permissão.
     * @param string $modulo chave de src/Models/Permissao.php (pedidos, estoque, contas…)
     */
    public static function requirePermissao(string $modulo, string $acao = 'ver'): void
    {
        self::requireLogin();
        require_once __DIR__ . '/PermissaoService.php';

        if (!PermissaoService::pode($modulo, $acao)) {
            header('Location: /foodhub/public/erro.php?tipo=acesso_negado');
            exit;
        }
    }
}