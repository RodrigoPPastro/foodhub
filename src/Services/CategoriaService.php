<?php

require_once __DIR__ . '/../Repositories/CategoriaRepository.php';
require_once __DIR__ . '/RegraNegocioException.php';

/** RF13 — regras de CATEGORIA. */
class CategoriaService
{
    public static function listar(): array
    {
        return array_map(fn($c) => [
            'id_categoria' => (int) $c['id_categoria'], 'nome' => $c['nome'],
            'descricao' => $c['descricao'], 'total_produtos' => (int) $c['total_produtos'],
        ], CategoriaRepository::listar());
    }

    public static function salvar(array $e): array
    {
        $id = (int) ($e['id_categoria'] ?? 0);
        $nome = trim((string) ($e['nome'] ?? ''));
        $descricao = trim((string) ($e['descricao'] ?? ''));

        $len = mb_strlen($nome, 'UTF-8');
        if ($len < 2 || $len > 60) {
            throw new RegraNegocioException('O nome da categoria deve ter de 2 a 60 caracteres.');
        }
        if (mb_strlen($descricao, 'UTF-8') > 200) {
            throw new RegraNegocioException('A descrição pode ter no máximo 200 caracteres.');
        }
        if (CategoriaRepository::nomeExiste($nome, $id)) {
            throw new RegraNegocioException('Já existe uma categoria com este nome.');
        }
        $descricao = $descricao === '' ? null : $descricao;

        if ($id > 0) {
            if (CategoriaRepository::buscar($id) === null) {
                throw new RegraNegocioException('Categoria não encontrada.');
            }
            CategoriaRepository::atualizar($id, $nome, $descricao);
        } else {
            $id = CategoriaRepository::inserir($nome, $descricao);
        }
        return ['id_categoria' => $id];
    }

    public static function excluir(int $id): array
    {
        if (CategoriaRepository::buscar($id) === null) {
            throw new RegraNegocioException('Categoria não encontrada.');
        }
        $total = CategoriaRepository::contarProdutos($id);
        if ($total > 0) {
            throw new RegraNegocioException("Não é possível excluir: a categoria possui $total produto(s). Mova ou exclua os produtos antes.");
        }
        CategoriaRepository::excluir($id);
        return ['id_categoria' => $id];
    }
}
