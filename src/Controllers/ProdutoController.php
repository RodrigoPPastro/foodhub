<?php

require_once __DIR__ . '/../Models/ProdutoModel.php';

class ProdutoController
{
    public function listar(): array
    {
        return ProdutoModel::listar();
    }

    public function buscarPorId(int $id): ?array
    {
        return ProdutoModel::buscarPorId($id);
    }

    public function cadastrar(
        int $idCategoria,
        string $nome,
        string $descricao,
        string $foto,
        float $precoVenda,
        int $ativo
    ): bool {
        return ProdutoModel::cadastrar(
            $idCategoria,
            $nome,
            $descricao,
            $foto,
            $precoVenda,
            $ativo
        );
    }

    public function atualizar(
        int $id,
        int $idCategoria,
        string $nome,
        string $descricao,
        string $foto,
        float $precoVenda,
        int $ativo
    ): bool {
        return ProdutoModel::atualizar(
            $id,
            $idCategoria,
            $nome,
            $descricao,
            $foto,
            $precoVenda,
            $ativo
        );
    }

    public function excluir(int $id): bool
    {
        return ProdutoModel::excluir($id);
    }
}