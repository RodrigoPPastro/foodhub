<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Models/Usuario.php';

AuthGuard::requireMaster();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id_usuario'] ?? 0);
    if ($id > 0) {
        Usuario::excluir($id);
    }
}

header('Location: /public/consulta_usuario.php');
exit;
