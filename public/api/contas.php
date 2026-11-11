<?php
/**
 * Contas a pagar / a receber e fluxo de caixa (Master).
 *   GET  ?tipo=pagar|receber[&situacao=aberta|vencida|baixada|cancelada|todas&de&ate&busca]
 *   GET  ?visao=fluxo[&de&ate]          fluxo de caixa do período (padrão: mês atual)
 *   GET  ?visao=canais                  comissão e prazo de repasse de cada plataforma
 *   POST {"acao":"salvar", id_conta?, tipo, descricao, categoria, pessoa?, valor, data_vencimento, observacao?}
 *   POST {"acao":"baixar", id_conta, data_baixa?, valor?} | {"acao":"baixar_lote", ids:[…], data_baixa?}
 *   POST {"acao":"estornar"|"cancelar", id_conta}
 *   POST {"acao":"salvar_canal", codigo, comissao_percentual, prazo_repasse_dias}
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Services/SessionService.php';
require_once __DIR__ . '/../../src/Controllers/ContaController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);
ApiGuard::requirePermissao('contas');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ApiGuard::exigirAjaxJson();
    $entrada = ApiGuard::lerJson();
    $acao = $entrada['acao'] ?? '';
    ApiGuard::requirePermissao('contas', match ($acao) {
        'salvar'   => !empty($entrada['id_conta']) ? 'alterar' : 'incluir',
        'cancelar' => 'excluir',
        default    => 'alterar',      // baixar, baixar em lote, estornar, comissão/prazo dos canais
    });
    $idUsuario = SessionService::usuarioIdAtual();
    ApiGuard::responder(match ($entrada['acao'] ?? '') {
        'salvar'       => ContaController::salvar($entrada),
        'baixar'       => ContaController::baixar($entrada, $idUsuario),
        'baixar_lote'  => ContaController::baixarLote($entrada, $idUsuario),
        'estornar'     => ContaController::estornar($entrada),
        'cancelar'     => ContaController::cancelar($entrada),
        'salvar_canal' => ContaController::salvarCanal($entrada),
        default        => ['http' => 400, 'sucesso' => false, 'mensagem' => 'Ação inválida.'],
    });
}

ApiGuard::responder(match ($_GET['visao'] ?? '') {
    'fluxo'  => ContaController::fluxo($_GET),
    'canais' => ContaController::canais(),
    default  => ContaController::listar($_GET),
});
