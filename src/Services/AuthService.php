<?php

require_once __DIR__ . '/../Repositories/UsuarioRepository.php';
require_once __DIR__ . '/PasswordService.php';
require_once __DIR__ . '/TwoFactorService.php';

class AuthService
{
    public static function autenticar(string $login, string $senha): array
    {
        $usuario = UsuarioRepository::buscarPorLogin($login);

        if ($usuario === null) {
            return [
                'status' => 'credenciais_invalidas'
            ];
        }

        if (!PasswordService::verify($senha, $usuario['senha_hash'])) {
            return [
                'status' => 'credenciais_invalidas'
            ];
        }

        $pergunta = TwoFactorService::iniciar($usuario);

        if ($pergunta === null) {
            return [
                'status' => '2fa_indisponivel'
            ];
        }

        return [
            'status' => '2fa_pendente',
            'usuario_id' => $usuario['id_usuario'],
            'pergunta' => $pergunta
        ];
    }
}