<?php

require_once __DIR__ . '/../Repositories/ProdutoRepository.php';

class ProdutoModel
{
    public static function listar(): array
    {
        return ProdutoRepository::listar();
    }

    public static function buscarPorId(int $id): ?array
    {
        return ProdutoRepository::buscarPorId($id);
    }

    public static function cadastrar(
        int $idCategoria,
        string $nome,
        string $descricao,
        string $foto,
        float $precoVenda,
        int $ativo
    ): bool {
        return ProdutoRepository::cadastrar(
            $idCategoria,
            $nome,
            $descricao,
            $foto,
            $precoVenda,
            $ativo
        );
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
        return ProdutoRepository::atualizar(
            $id,
            $idCategoria,
            $nome,
            $descricao,
            $foto,
            $precoVenda,
            $ativo
        );
    }

    public static function excluir(int $id): bool
    {
        return ProdutoRepository::excluir($id);
    }
}