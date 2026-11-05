<?php

require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/CategoriaController.php';

AuthGuard::requireMaster();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: categorias.php');
    exit;
}

$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');

if ($nome === '') {
    header('Location: categorias.php?erro=nome');
    exit;
}

if (mb_strlen($nome) > 80 || mb_strlen($descricao) > 255) {
    header('Location: categorias.php?erro=tamanho');
    exit;
}

$controller = new CategoriaController();

try {
    $controller->cadastrar($nome, $descricao);
    header('Location: categorias.php?sucesso=1');
    exit;
} catch (PDOException $e) {
    header('Location: Categorias.php?erro=cadastro');
    exit;
}
