<?php
require_once __DIR__ . '/../Models/Autenticacao2FA.php';
require_once __DIR__ . '/../Models/LogAcesso.php';
require_once __DIR__ . '/PasswordService.php';

/**
 * Controla o fluxo do 2FA (RF05): sorteio de pergunta, verificação da
 * resposta e as 3 tentativas antes de forçar novo login.
 *
 * Estado fica em SESSÃO (não no banco), guardado num "estado pendente"
 * separado do login definitivo — só depois de validado é que
 * SessionService::autenticarUsuario() é chamado.
 */

class TwoFactorService
{
    private const MAX_TENTATIVAS = 3;

    /**
     * Chamado logo após login/senha OK. Sorteia a pergunta e guarda
     * o "pendente" de autenticação na sessão.
     */
    public static function iniciar(array $usuario): ?string
    {
        $sorteada = Autenticacao2FA::sortearPerguntaDoUsuario($usuario['id_usuario']);

        if ($sorteada === null) {
            // Usuário sem 2FA cadastrado — falha de integridade de dados.
            return null;
        }

        $_SESSION['2fa_pendente'] = [
            'id_usuario'    => $usuario['id_usuario'],
            'pergunta'      => $sorteada['pergunta'],
            'resposta_hash' => $sorteada['resposta_hash'],
            'tentativas'    => 0,
        ];

        return $sorteada['pergunta'];
    }

    public static function perguntaAtual(): ?string
    {
        return $_SESSION['2fa_pendente']['pergunta'] ?? null;
    }

    public static function existePendente(): bool
    {
        return isset($_SESSION['2fa_pendente']);
    }

    /**
     * Retorna um array com:
     *  - 'status' => 'sucesso' | 'tentativa_invalida' | 'bloqueado'
     *  - 'usuario_id' (quando sucesso, pra SessionService autenticar)
     */
    public static function validarResposta(string $respostaDigitada): array
    {
        if (!self::existePendente()) {
            return ['status' => 'sem_pendencia'];
        }

        $pendente = $_SESSION['2fa_pendente'];
        $normalizada = PasswordService::normalizarResposta($respostaDigitada);
        $correta = PasswordService::verify($normalizada, $pendente['resposta_hash']);

        if ($correta) {
            LogAcesso::registrar($pendente['id_usuario'], $pendente['pergunta'], 'sucesso');
            unset($_SESSION['2fa_pendente']);
            return ['status' => 'sucesso', 'usuario_id' => $pendente['id_usuario']];
        }

        $_SESSION['2fa_pendente']['tentativas']++;

        if ($_SESSION['2fa_pendente']['tentativas'] >= self::MAX_TENTATIVAS) {
            LogAcesso::registrar($pendente['id_usuario'], $pendente['pergunta'], 'falha');
            unset($_SESSION['2fa_pendente']);
            return ['status' => 'bloqueado'];
        }

        return [
            'status'              => 'tentativa_invalida',
            'tentativas_restantes' => self::MAX_TENTATIVAS - $_SESSION['2fa_pendente']['tentativas'],
        ];
    }
}
