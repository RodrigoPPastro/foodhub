<?php

require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/ProdutoController.php';

AuthGuard::requireMaster();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Produtos.php');
    exit;
}

$idCategoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);

$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$foto = trim($_POST['foto'] ?? '');
$precoTexto = trim($_POST['preco_venda'] ?? '');
$ativo = filter_input(INPUT_POST, 'ativo', FILTER_VALIDATE_INT);

$precoTexto = str_replace(',', '.', $precoTexto);

if (
    !$idCategoria ||
    $nome === '' ||
    !is_numeric($precoTexto) ||
    (float) $precoTexto <= 0 ||
    !in_array($ativo, [0, 1], true)
) {
    header('Location: Produtos.php?erro=1');
    exit;
}

if (
    mb_strlen($nome) > 120 ||
    mb_strlen($foto) > 255
) {
    header('Location: Produtos.php?erro=1');
    exit;
}

$controller = new ProdutoController();

try {

    $resultado = $controller->cadastrar(
        $idCategoria,
        $nome,
        $descricao,
        $foto,
        (float) $precoTexto,
        $ativo
    );

    if ($resultado) {
        header('Location: Produtos.php?sucesso=1');
        exit;
    }

    header('Location: Produtos.php?erro=1');
    exit;

} catch (Throwable $e) {

    die(
        'Erro ao cadastrar produto: ' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
    );

}