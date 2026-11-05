<?php

require_once __DIR__ . '/../Models/CategoriaModel.php';

class CategoriaController
{
    public function listar(): array
    {
        return CategoriaModel::listar();
    }

    public function buscarPorId(int $id): ?array
    {
        return CategoriaModel::buscarPorId($id);
    }

    public function cadastrar(string $nome, string $descricao): bool
    {
        return CategoriaModel::cadastrar($nome, $descricao);
    }

    public function atualizar(int $id, string $nome, string $descricao): bool
    {
        return CategoriaModel::atualizar($id, $nome, $descricao);
    }

    public function excluir(int $id): bool
    {
        return CategoriaModel::excluir($id);
    }
}