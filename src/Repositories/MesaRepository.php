<?php

require_once __DIR__ . '/../../config/database.php';

/** Mesas do salão. */
class MesaRepository
{
    public static function listar(): array
    {
        return Database::getConnection()->query('SELECT id_mesa, numero, capacidade, status FROM mesa ORDER BY numero')->fetchAll();
    }

    public static function buscar(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM mesa WHERE id_mesa = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function numeroExiste(int $numero, int $ignorarId = 0): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM mesa WHERE numero = :n AND id_mesa <> :id');
        $stmt->execute(['n' => $numero, 'id' => $ignorarId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function inserir(int $numero, int $capacidade): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare('INSERT INTO mesa (numero, capacidade) VALUES (:n, :c)')->execute(['n' => $numero, 'c' => $capacidade]);
        return (int) $pdo->lastInsertId();
    }

    public static function atualizar(int $id, int $numero, int $capacidade): void
    {
        Database::getConnection()->prepare('UPDATE mesa SET numero = :n, capacidade = :c WHERE id_mesa = :id')->execute(['n' => $numero, 'c' => $capacidade, 'id' => $id]);
    }

    public static function definirStatus(int $id, string $status): void
    {
        Database::getConnection()->prepare('UPDATE mesa SET status = :s WHERE id_mesa = :id')->execute(['s' => $status, 'id' => $id]);
    }

    public static function excluir(int $id): void
    {
        Database::getConnection()->prepare('DELETE FROM mesa WHERE id_mesa = :id')->execute(['id' => $id]);
    }
}
