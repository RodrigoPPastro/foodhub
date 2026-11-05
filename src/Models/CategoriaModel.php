<?php

require_once __DIR__ . '/../Repositories/CategoriaRepository.php';

class CategoriaModel
{
    public static function listar(): array
    {
        return CategoriaRepository::listar();
    }

    public static function buscarPorId(int $id): ?array
    {
        return CategoriaRepository::buscarPorId($id);
    }

    public static function cadastrar(string $nome, string $descricao): bool
    {
        return CategoriaRepository::cadastrar($nome, $descricao);
    }

    public static function atualizar(
        int $id,
        string $nome,
        string $descricao
    ): bool {
        return CategoriaRepository::atualizar($id, $nome, $descricao);
    }

    public static function excluir(int $id): bool
    {
        return CategoriaRepository::excluir($id);
    }
}