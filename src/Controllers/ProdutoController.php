<?php

require_once __DIR__ . '/RespostaController.php';
require_once __DIR__ . '/../Services/ProdutoService.php';

/** RF01/RF13/RF14 */
class ProdutoController
{
    public static function listar(array $get): array { return RespostaController::executar(fn() => ProdutoService::listar($get)); }
    public static function cardapio(): array { return RespostaController::executar(fn() => ProdutoService::cardapio()); }
    public static function salvar(array $e): array { return RespostaController::executar(fn() => ProdutoService::salvar($e)); }
    public static function definirAtivo(array $e): array
    {
        return RespostaController::executar(fn() => ProdutoService::definirAtivo((int) ($e['id_produto'] ?? 0), !empty($e['ativo'])));
    }
    public static function excluir(array $e): array { return RespostaController::executar(fn() => ProdutoService::excluir((int) ($e['id_produto'] ?? 0))); }
    public static function ficha(array $get): array { return RespostaController::executar(fn() => ProdutoService::fichaTecnica((int) ($get['id_produto'] ?? 0))); }
    public static function salvarFicha(array $e): array
    {
        return RespostaController::executar(fn() => ProdutoService::salvarFicha(
            (int) ($e['id_produto'] ?? 0), is_array($e['itens'] ?? null) ? $e['itens'] : []
        ));
    }
    public static function foto(int $id, ?array $arquivo): array { return RespostaController::executar(fn() => ProdutoService::salvarFoto($id, $arquivo)); }
    public static function removerFoto(int $id): array { return RespostaController::executar(fn() => ProdutoService::removerFoto($id)); }
}
