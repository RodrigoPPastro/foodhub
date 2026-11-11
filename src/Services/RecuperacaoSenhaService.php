<?php

require_once __DIR__ . '/../Repositories/UsuarioRepository.php';
require_once __DIR__ . '/../Repositories/Autenticacao2FARepository.php';
require_once __DIR__ . '/LogAcessoService.php';
require_once __DIR__ . '/PasswordService.php';
require_once __DIR__ . '/SessionService.php';
require_once __DIR__ . '/ValidationService.php';

class RecuperacaoSenhaService
{
    private const MAX_TENTATIVAS = 3;

    /**
     * Inicia o fluxo de recuperação para um usuário comum.
     *
     * Não retorna detalhes que permitam diferenciar um login inexistente
     * de um usuário sem recuperação disponível.
     */
    public static function iniciar(string $login): array
    {
        $login = trim($login);

        if ($login === '') {
            return ['status' => 'recuperacao_indisponivel'];
        }

        $usuario = UsuarioRepository::buscarPorLogin($login);

        if ($usuario === null || !in_array($usuario['perfil'] ?? null, ['comum', 'funcionario'], true)) {
            return ['status' => 'recuperacao_indisponivel'];
        }

        $sorteada = Autenticacao2FARepository::sortearPerguntaDoUsuario(
            (int) $usuario['id_usuario']
        );

        if ($sorteada === null) {
            return ['status' => 'recuperacao_indisponivel'];
        }

        SessionService::iniciarRecuperacaoPendente(
            (int) $usuario['id_usuario'],
            (int) $sorteada['id_2fa'],
            $sorteada['pergunta']
        );

        return [
            'status' => 'pergunta_pendente',
            'usuario_id' => (int) $usuario['id_usuario'],
            'pergunta' => $sorteada['pergunta'],
        ];
    }

    public static function perguntaAtual(): ?string
    {
        $pendente = SessionService::dadosRecuperacaoPendente();
        return $pendente['pergunta'] ?? null;
    }

    public static function existePendente(): bool
    {
        return SessionService::dadosRecuperacaoPendente() !== null;
    }

    /**
     * Valida a resposta da pergunta sorteada.
     * A resposta nunca é armazenada na sessão.
     */
    public static function validarResposta(string $respostaDigitada): array
    {
        $pendente = SessionService::dadosRecuperacaoPendente();

        if ($pendente === null) {
            return ['status' => 'sem_pendencia'];
        }

        $registro = Autenticacao2FARepository::buscarPorIdEUsuario(
            (int) $pendente['id_2fa'],
            (int) $pendente['id_usuario']
        );

        if ($registro === null) {
            SessionService::limparRecuperacao();
            return ['status' => 'sem_pendencia'];
        }

        $normalizada = PasswordService::normalizarResposta($respostaDigitada);
        $correta = PasswordService::verify(
            $normalizada,
            $registro['resposta_hash']
        );

        if ($correta) {
            LogAcessoService::registrar(
                (int) $pendente['id_usuario'],
                'recuperacao_senha',
                'sucesso'
            );

            SessionService::autorizarRecuperacao(
                (int) $pendente['id_usuario']
            );

            return [
                'status' => 'sucesso',
                'usuario_id' => (int) $pendente['id_usuario'],
            ];
        }

        $tentativas = SessionService::incrementarTentativasRecuperacao();

        if ($tentativas >= self::MAX_TENTATIVAS) {
            LogAcessoService::registrar(
                (int) $pendente['id_usuario'],
                'recuperacao_senha',
                'falha'
            );

            SessionService::limparRecuperacao();

            return ['status' => 'bloqueado'];
        }

        return [
            'status' => 'tentativa_invalida',
            'tentativas_restantes' => self::MAX_TENTATIVAS - $tentativas,
        ];
    }

    /**
     * Redefine a senha somente após a resposta de recuperação ter sido validada.
     * Depois da troca, o usuário volta para o login normal e deverá passar pelo 2FA.
     */
    public static function redefinirSenha(
        string $novaSenha,
        string $confirmarSenha
    ): array {
        $autorizacao = SessionService::recuperacaoAutorizada();

        if ($autorizacao === null) {
            return [
                'sucesso' => false,
                'status' => 'nao_autorizada',
                'mensagem' => 'A autorização para redefinir a senha expirou ou é inválida.',
            ];
        }

        if ($novaSenha !== $confirmarSenha) {
            return [
                'sucesso' => false,
                'status' => 'senhas_diferentes',
                'mensagem' => 'A confirmação não confere com a nova senha.',
            ];
        }

        if (!ValidationService::validarSenha($novaSenha)) {
            return [
                'sucesso' => false,
                'status' => 'senha_invalida',
                'mensagem' => 'A nova senha deve ter exatamente 8 caracteres alfabéticos.',
            ];
        }

        $idUsuario = (int) $autorizacao['id_usuario'];
        $usuario = UsuarioRepository::buscarPorId($idUsuario);

        if ($usuario === null || !in_array($usuario['perfil'] ?? null, ['comum', 'funcionario'], true)) {
            SessionService::limparRecuperacao();

            return [
                'sucesso' => false,
                'status' => 'usuario_invalido',
                'mensagem' => 'Não foi possível concluir a recuperação da senha.',
            ];
        }

        $novoHash = PasswordService::hash($novaSenha);

        $atualizada = UsuarioRepository::atualizarSenha(
            $idUsuario,
            $novoHash
        );

        if (!$atualizada) {
            return [
                'sucesso' => false,
                'status' => 'erro_atualizacao',
                'mensagem' => 'Não foi possível atualizar a senha. Tente novamente.',
            ];
        }

        SessionService::limparRecuperacao();

        return [
            'sucesso' => true,
            'status' => 'senha_redefinida',
            'mensagem' => 'Senha redefinida com sucesso. Faça login novamente.',
        ];
    }
}
