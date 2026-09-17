<?php

require_once __DIR__ . '/../Repositories/LogAcessoRepository.php';

class LogAcessoService
{
    public static function registrar(int $idUsuario, string $tipo2fa, string $resultado): void
    {
        LogAcessoRepository::registrar($idUsuario, $tipo2fa, $resultado);
    }

    public static function listar(?string $nome = null, ?string $cpf = null): array
    {
        return LogAcessoRepository::listar($nome, $cpf);
    }
}
