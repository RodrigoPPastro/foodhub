<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/ProdutoController.php';

AuthGuard::requireMaster();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: produtos.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: produtos.php?erro=id');
    exit;
}

$controller = new ProdutoController();

try {
    if (!$controller->buscarPorId($id)) {
        header('Location: produtos.php?erro=nao_encontrado');
        exit;
    }

    $excluido = $controller->excluir($id);
    header('Location: produtos.php?' . ($excluido ? 'sucesso=excluido' : 'erro=exclusao'));
    exit;
} catch (Throwable $e) {
    error_log('Erro ao excluir produto: ' . $e->getMessage());
    header('Location: produtos.php?erro=exclusao');
    exit;
}
