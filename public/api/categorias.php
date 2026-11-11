<?php
/**
 * RF13 — categorias do cardápio (Master).
 *   GET                                  -> lista (com total de produtos)
 *   POST {"acao":"salvar", id_categoria?, nome, descricao} | {"acao":"excluir", id_categoria}
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Controllers/CategoriaController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ApiGuard::requirePermissao('categorias');
    ApiGuard::exigirAjaxJson();
    $entrada = ApiGuard::lerJson();
    $acao = $entrada['acao'] ?? '';
    ApiGuard::requirePermissao('categorias', $acao === 'excluir' ? 'excluir' : (!empty($entrada['id_categoria']) ? 'alterar' : 'incluir'));
    ApiGuard::responder(match ($entrada['acao'] ?? '') {
        'salvar'  => CategoriaController::salvar($entrada),
        'excluir' => CategoriaController::excluir($entrada),
        default   => ['http' => 400, 'sucesso' => false, 'mensagem' => 'Ação inválida.'],
    });
}
ApiGuard::requirePermissao(['categorias', 'produtos']);   // a tela de Produtos lista as categorias
ApiGuard::responder(CategoriaController::listar());
