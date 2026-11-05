<?php

require_once __DIR__ . '/../../config/database.php';

class CategoriaRepository
{
    public static function listar(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query(
            'SELECT * FROM categoria ORDER BY nome ASC'
        );

        return $stmt->fetchAll();
    }

    public static function buscarPorId(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM categoria WHERE id_categoria = :id'
        );

        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function cadastrar(string $nome, string $descricao): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO categoria (nome, descricao)
             VALUES (:nome, :descricao)'
        );

        return $stmt->execute([
            'nome' => $nome,
            'descricao' => $descricao
        ]);
    }

    public static function atualizar(
    int $id,
    string $nome,
    string $descricao
): bool {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare(
        'UPDATE categoria
         SET nome = :nome,
             descricao = :descricao
         WHERE id_categoria = :id'
    );

    return $stmt->execute([
        'id' => $id,
        'nome' => $nome,
        'descricao' => $descricao
    ]);
}
    public static function excluir(int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'DELETE FROM categoria WHERE id_categoria = :id'
        );

        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}