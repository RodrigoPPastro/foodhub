<?php

require_once __DIR__ . '/../Repositories/UsuarioRepository.php';
require_once __DIR__ . '/PasswordService.php';

class UsuarioService
{
    public static function buscarPorId(int $idUsuario): ?array
    {
        return UsuarioRepository::buscarPorId($idUsuario);
    }

    public static function loginJaExiste(string $login): bool
    {
        return UsuarioRepository::loginJaExiste($login);
    }

    public static function cpfJaExiste(string $cpf): bool
    {
        return UsuarioRepository::cpfJaExiste($cpf);
    }

    public static function emailJaExiste(string $email): bool
    {
        return UsuarioRepository::emailJaExiste($email);
    }

    /**
     * RF06 — consulta usuários comuns por nome.
     */
    public static function consultarPorNome(string $termo): array
    {
        return UsuarioRepository::buscarPorNomeSubstring(trim($termo));
    }

    /**
     * RF06 — exclui usuário comum.
     * A autorização é responsabilidade da camada de acesso (AuthGuard).
     */
    public static function excluir(int $idUsuario): bool
    {
        if ($idUsuario <= 0) {
            return false;
        }

        return UsuarioRepository::excluir($idUsuario);
    }

    /**
     * RF07 — altera a senha do próprio usuário comum.
     */
    public static function alterarSenha(
        int $idUsuario,
        string $senhaAtual,
        string $novaSenha,
        string $confirmarSenha
    ): array {
        $usuario = UsuarioRepository::buscarPorId($idUsuario);

        if ($usuario === null) {
            return ['sucesso' => false, 'mensagem' => 'Usuário não encontrado.'];
        }

        if (!PasswordService::verify($senhaAtual, $usuario['senha_hash'])) {
            return ['sucesso' => false, 'mensagem' => 'Senha atual incorreta.'];
        }

        if (strlen($novaSenha) !== 8 || !ctype_alpha($novaSenha)) {
            return [
                'sucesso' => false,
                'mensagem' => 'A nova senha deve ter exatamente 8 caracteres alfabéticos.'
            ];
        }

        if ($novaSenha !== $confirmarSenha) {
            return [
                'sucesso' => false,
                'mensagem' => 'A confirmação não confere com a nova senha.'
            ];
        }

        $atualizada = UsuarioRepository::atualizarSenha(
            $idUsuario,
            PasswordService::hash($novaSenha)
        );

        return [
            'sucesso' => $atualizada,
            'mensagem' => $atualizada
                ? 'Senha alterada com sucesso.'
                : 'Não foi possível alterar a senha.'
        ];
    }
}
