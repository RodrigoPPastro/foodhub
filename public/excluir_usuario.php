<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Controllers/UsuarioController.php';

AuthGuard::requireMaster();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /foodhub/public/consulta_usuario.php');
    exit;
}

$id     = (int) ($_POST['id_usuario'] ?? 0);
$busca  = trim($_POST['busca_retorno'] ?? '');
$ok     = UsuarioController::excluir($id);

SessionService::setFlash('usuario_exclusao', [
    'sucesso'  => $ok,
    'mensagem' => $ok
        ? 'Usuário excluído com sucesso.'
        : 'Não foi possível excluir o usuário.',
]);

$qs = $busca !== '' ? '?busca=' . urlencode($busca) : '';
header('Location: /foodhub/public/consulta_usuario.php' . $qs);
exit;
