<?php

require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/CategoriaController.php';

AuthGuard::requireMaster();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Categorias.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');

if (!$id || $nome === '') {
    die('ID ou nome da categoria inválido.');
}

if (mb_strlen($nome) > 80 || mb_strlen($descricao) > 255) {
    die('O nome ou a descrição ultrapassou o limite permitido.');
}

$controller = new CategoriaController();

try {
    $categoria = $controller->buscarPorId($id);

    if (!$categoria) {
        die('Categoria não encontrada no banco de dados.');
    }

    $resultado = $controller->atualizar($id, $nome, $descricao);

    if (!$resultado) {
        die('A atualização não foi executada. Verifique os dados enviados.');
    }

    header('Location: Categorias.php?sucesso=editada');
    exit;

} catch (Throwable $e) {
    die('Erro ao atualizar categoria: ' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}