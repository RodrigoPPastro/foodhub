<?php
/**
 * RF13/RF14 — produtos do cardápio, ficha técnica e foto.
 *   GET  ?visao=cardapio                    -> (qualquer usuário logado) cardápio ativo por categoria
 *   GET  [?id_categoria&ativo&busca]        -> (Master) lista com custo e margem
 *   GET  ?ficha=1&id_produto=N              -> (Master) ficha técnica + custo/margem
 *   POST JSON {"acao":"salvar|ativar|excluir|salvar_ficha|remover_foto", ...}
 *   POST multipart: id_produto + foto=<arquivo>                                  (Master)
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Controllers/ProdutoController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['visao'] ?? '') === 'cardapio') {
        ApiGuard::requireLogin();
        ApiGuard::responder(ProdutoController::cardapio());
    }
    ApiGuard::requirePermissao('produtos');
    if (!empty($_GET['ficha'])) {
        ApiGuard::responder(ProdutoController::ficha($_GET));
    }
    ApiGuard::responder(ProdutoController::listar($_GET));
}

ApiGuard::requirePermissao('produtos');

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0) {
    ApiGuard::requirePermissao('produtos', 'alterar');   // enviar foto
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'FoodHub') { // anti-CSRF
        ApiGuard::responder(['http' => 400, 'sucesso' => false, 'mensagem' => 'Requisição inválida.']);
    }
    ApiGuard::responder(ProdutoController::foto((int) ($_POST['id_produto'] ?? 0), $_FILES['foto'] ?? null));
}

ApiGuard::exigirAjaxJson();
$entrada = ApiGuard::lerJson();
$acao = $entrada['acao'] ?? '';
ApiGuard::requirePermissao('produtos', match ($acao) {
    'salvar'  => !empty($entrada['id_produto']) ? 'alterar' : 'incluir',
    'excluir' => 'excluir',
    default   => 'alterar',      // ativar/inativar, ficha técnica, remover foto
});
ApiGuard::responder(match ($entrada['acao'] ?? '') {
    'salvar'       => ProdutoController::salvar($entrada),
    'ativar'       => ProdutoController::definirAtivo($entrada),
    'excluir'      => ProdutoController::excluir($entrada),
    'salvar_ficha' => ProdutoController::salvarFicha($entrada),
    'remover_foto' => ProdutoController::removerFoto((int) ($entrada['id_produto'] ?? 0)),
    default        => ['http' => 400, 'sucesso' => false, 'mensagem' => 'Ação inválida.'],
});
