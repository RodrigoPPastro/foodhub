<?php

require_once __DIR__ . '/../../config/database.php';

class ProdutoRepository
{
    public static function listar(): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->query(
            'SELECT p.*, c.nome AS nome_categoria
             FROM produto p
             INNER JOIN categoria c
             ON p.id_categoria = c.id_categoria
             ORDER BY p.nome ASC'
        );

        return $stmt->fetchAll();
    }

    public static function buscarPorId(int $id): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'SELECT * FROM produto
             WHERE id_produto = :id'
        );

        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function cadastrar(
        int $idCategoria,
        string $nome,
        string $descricao,
        string $foto,
        float $precoVenda,
        int $ativo
    ): bool {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'INSERT INTO produto
            (id_categoria, nome, descricao, foto, preco_venda, ativo)
            VALUES
            (:id_categoria, :nome, :descricao, :foto, :preco_venda, :ativo)'
        );

        return $stmt->execute([
            'id_categoria' => $idCategoria,
            'nome' => $nome,
            'descricao' => $descricao,
            'foto' => $foto,
            'preco_venda' => $precoVenda,
            'ativo' => $ativo
        ]);
    }

    public static function atualizar(
        int $id,
        int $idCategoria,
        string $nome,
        string $descricao,
        string $foto,
        float $precoVenda,
        int $ativo
    ): bool {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'UPDATE produto
             SET id_categoria = :id_categoria,
                 nome = :nome,
                 descricao = :descricao,
                 foto = :foto,
                 preco_venda = :preco_venda,
                 ativo = :ativo
             WHERE id_produto = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'id_categoria' => $idCategoria,
            'nome' => $nome,
            'descricao' => $descricao,
            'foto' => $foto,
            'preco_venda' => $precoVenda,
            'ativo' => $ativo
        ]);
    }

    public static function excluir(int $id): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            'DELETE FROM produto
             WHERE id_produto = :id'
        );

        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}