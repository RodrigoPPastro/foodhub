<?php

require_once __DIR__ . '/../Services/UsuarioService.php';

class UsuarioController
{
    /**
     * RF06 — prepara os dados da consulta de usuários.
     */
    public static function consultar(string $termo): array
    {
        return UsuarioService::consultarPorNome($termo);
    }

    /**
     * RF06 — processa a exclusão solicitada pela tela.
     */
    public static function excluir(int $idUsuario): bool
    {
        return UsuarioService::excluir($idUsuario);
    }

    /**
     * RF07 — processa a alteração de senha solicitada pelo formulário.
     */
    public static function alterarSenha(
        int $idUsuario,
        string $senhaAtual,
        string $novaSenha,
        string $confirmarSenha
    ): array {
        return UsuarioService::alterarSenha(
            $idUsuario,
            $senhaAtual,
            $novaSenha,
            $confirmarSenha
        );
    }
}
