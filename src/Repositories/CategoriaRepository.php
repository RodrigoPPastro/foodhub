<?php

require_once __DIR__ . '/../../config/database.php';

/** RF13 — acesso a dados de CATEGORIA. */
class CategoriaRepository
{
    public static function listar(): array
    {
        return Database::getConnection()->query(
            'SELECT c.id_categoria, c.nome, c.descricao, COUNT(p.id_produto) AS total_produtos
             FROM categoria c LEFT JOIN produto p ON p.id_categoria = c.id_categoria
             GROUP BY c.id_categoria, c.nome, c.descricao ORDER BY c.nome'
        )->fetchAll();
    }

    public static function buscar(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM categoria WHERE id_categoria = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function nomeExiste(string $nome, int $ignorarId = 0): bool
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM categoria WHERE LOWER(nome) = LOWER(:nome) AND id_categoria <> :id'
        );
        $stmt->execute(['nome' => $nome, 'id' => $ignorarId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function inserir(string $nome, ?string $descricao): int
    {
        $pdo = Database::getConnection();
        $pdo->prepare('INSERT INTO categoria (nome, descricao) VALUES (:nome, :descricao)')
            ->execute(['nome' => $nome, 'descricao' => $descricao]);
        return (int) $pdo->lastInsertId();
    }

    public static function atualizar(int $id, string $nome, ?string $descricao): void
    {
        Database::getConnection()
            ->prepare('UPDATE categoria SET nome = :nome, descricao = :descricao WHERE id_categoria = :id')
            ->execute(['nome' => $nome, 'descricao' => $descricao, 'id' => $id]);
    }

    public static function contarProdutos(int $id): int
    {
        $stmt = Database::getConnection()->prepare('SELECT COUNT(*) FROM produto WHERE id_categoria = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public static function excluir(int $id): void
    {
        Database::getConnection()->prepare('DELETE FROM categoria WHERE id_categoria = :id')->execute(['id' => $id]);
    }
}
