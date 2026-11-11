<?php
/**
 * RF15/RF16 — insumos e estoque (Master).
 *   GET  [?busca&baixo=1]                  -> insumos (com alerta abaixo do mínimo)
 *   GET  ?movimentos=1[&id_insumo&limite]  -> histórico de movimentações
 *   POST {"acao":"salvar|excluir|movimentar", ...}
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Services/SessionService.php';
require_once __DIR__ . '/../../src/Controllers/InsumoController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ApiGuard::requirePermissao('estoque');
    ApiGuard::exigirAjaxJson();
    $entrada = ApiGuard::lerJson();
    $acao = $entrada['acao'] ?? '';
    ApiGuard::requirePermissao('estoque', match (true) {
        $acao === 'excluir'    => 'excluir',
        $acao === 'movimentar' => 'alterar',              // entrada / saída / perda
        !empty($entrada['id_insumo']) => 'alterar',
        default                => 'incluir',
    });
    // Entrada de compra que também lança a conta a pagar: precisa poder incluir contas
    if ($acao === 'movimentar' && !empty($entrada['gerar_conta']['ativo'])) {
        ApiGuard::requirePermissao('contas', 'incluir');
    }
    $idUsuario = SessionService::usuarioIdAtual();
    ApiGuard::responder(match ($entrada['acao'] ?? '') {
        'salvar'     => InsumoController::salvar($entrada, $idUsuario),
        'excluir'    => InsumoController::excluir($entrada),
        'movimentar' => InsumoController::movimentar($entrada, $idUsuario),
        default      => ['http' => 400, 'sucesso' => false, 'mensagem' => 'Ação inválida.'],
    });
}
ApiGuard::requirePermissao(['estoque', 'produtos']);   // a ficha técnica (Produtos) lista os insumos
if (!empty($_GET['movimentos'])) {
    ApiGuard::requirePermissao('estoque');
    ApiGuard::responder(InsumoController::movimentos($_GET));
}
ApiGuard::responder(InsumoController::listar($_GET));
