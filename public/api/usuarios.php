<?php
/**
 * Funcionários e permissões (somente Master).
 *   GET  ?visao=catalogo              telas, ações e modelos prontos (Garçom, Cozinha, Bar, Caixa, Gerente)
 *   GET  ?visao=funcionarios          funcionários cadastrados e as telas que cada um vê
 *   GET  ?id_usuario=N                perfil e permissões de um usuário
 *   POST {"acao":"permissoes", id_usuario, perfil: comum|funcionario, permissoes: {modulo: [acoes]}}
 */
require_once __DIR__ . '/../../src/Services/ApiGuard.php';
require_once __DIR__ . '/../../src/Services/PermissaoService.php';
require_once __DIR__ . '/../../src/Controllers/RespostaController.php';

ApiGuard::exigirMetodo(['GET', 'POST']);
ApiGuard::requireMaster();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $visao = $_GET['visao'] ?? '';
    ApiGuard::responder(RespostaController::executar(fn() => match (true) {
        $visao === 'catalogo'     => PermissaoService::catalogo(),
        $visao === 'funcionarios' => PermissaoService::funcionarios(),
        default                   => PermissaoService::doUsuario((int) ($_GET['id_usuario'] ?? 0)),
    }));
}

ApiGuard::exigirAjaxJson();
$entrada = ApiGuard::lerJson();
if (($entrada['acao'] ?? '') !== 'permissoes') {
    ApiGuard::responder(['http' => 400, 'sucesso' => false, 'mensagem' => 'Ação inválida.']);
}
ApiGuard::responder(RespostaController::executar(fn() => PermissaoService::salvar(
    (int) ($entrada['id_usuario'] ?? 0),
    (string) ($entrada['perfil'] ?? ''),
    is_array($entrada['permissoes'] ?? null) ? $entrada['permissoes'] : []
)));
