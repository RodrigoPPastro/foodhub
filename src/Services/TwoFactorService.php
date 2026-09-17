<?php

require_once __DIR__ . '/../Repositories/Autenticacao2FARepository.php';
require_once __DIR__ . '/LogAcessoService.php';
require_once __DIR__ . '/PasswordService.php';
require_once __DIR__ . '/SessionService.php';

class TwoFactorService
{
    private const MAX_TENTATIVAS = 3;

    public static function iniciar(array $usuario): ?string
    {
        $sorteada = Autenticacao2FARepository::sortearPerguntaDoUsuario($usuario['id_usuario']);

        if ($sorteada === null) {
            return null;
        }

        SessionService::iniciar2faPendente(
            (int) $usuario['id_usuario'],
            (int) $sorteada['id_2fa'],
            $sorteada['pergunta']
        );

        return $sorteada['pergunta'];
    }

    public static function perguntaAtual(): ?string
    {
        $pendente = SessionService::dados2faPendente();
        return $pendente['pergunta'] ?? null;
    }

    public static function existePendente(): bool
    {
        return SessionService::dados2faPendente() !== null;
    }

    public static function validarResposta(string $respostaDigitada): array
    {
        $pendente = SessionService::dados2faPendente();

        if ($pendente === null) {
            return ['status' => 'sem_pendencia'];
        }

        // O hash da resposta nunca fica na sessão: ele é buscado novamente no banco.
        $registro = Autenticacao2FARepository::buscarPorIdEUsuario(
            (int) $pendente['id_2fa'],
            (int) $pendente['id_usuario']
        );

        if ($registro === null) {
            SessionService::limpar2faPendente();
            return ['status' => 'sem_pendencia'];
        }

        $normalizada = PasswordService::normalizarResposta($respostaDigitada);
        $correta = PasswordService::verify($normalizada, $registro['resposta_hash']);

        if ($correta) {
            LogAcessoService::registrar($pendente['id_usuario'], $registro['pergunta'], 'sucesso');
            SessionService::limpar2faPendente();
            return ['status' => 'sucesso', 'usuario_id' => $pendente['id_usuario']];
        }

        $tentativas = SessionService::incrementarTentativas2fa();

        if ($tentativas >= self::MAX_TENTATIVAS) {
            LogAcessoService::registrar($pendente['id_usuario'], $registro['pergunta'], 'falha');
            SessionService::limpar2faPendente();
            return ['status' => 'bloqueado'];
        }

        return [
            'status' => 'tentativa_invalida',
            'tentativas_restantes' => self::MAX_TENTATIVAS - $tentativas,
        ];
    }
}
