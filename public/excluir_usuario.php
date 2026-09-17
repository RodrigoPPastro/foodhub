<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/UsuarioController.php';

AuthGuard::requireMaster();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /foodhub/public/consulta_usuario.php');
    exit;
}

$id = (int) ($_POST['id_usuario'] ?? 0);
UsuarioController::excluir($id);

header('Location: /foodhub/public/consulta_usuario.php');
exit;
