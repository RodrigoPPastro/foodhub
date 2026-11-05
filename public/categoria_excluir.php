<?php

require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/CategoriaController.php';

AuthGuard::requireMaster();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: Categorias.php?erro=id');
    exit;
}

$controller = new CategoriaController();

try {

    $categoria = $controller->buscarPorId($id);

    if (!$categoria) {
        header('Location: Categorias.php?erro=nao_encontrada');
        exit;
    }

    $resultado = $controller->excluir($id);

    if ($resultado) {
        header('Location: Categorias.php?sucesso=excluida');
        exit;
    }

    header('Location: Categorias.php?erro=exclusao');
    exit;

} catch (Throwable $e) {
    header('Location: Categorias.php?erro=exclusao');
    exit;
}