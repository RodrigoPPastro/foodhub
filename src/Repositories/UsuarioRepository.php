<?php

require_once __DIR__ . '/../../config/database.php';

class UsuarioRepository
{
    public static function buscarPorLogin(string $login): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'SELECT *
             FROM usuario
             WHERE login = :login
             LIMIT 1'
        );

        $stmt->execute([
            'login' => $login
        ]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    public static function buscarPorId(int $idUsuario): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'SELECT *
             FROM usuario
             WHERE id_usuario = :id
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $idUsuario
        ]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }
}