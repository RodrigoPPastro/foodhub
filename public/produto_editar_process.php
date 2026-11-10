
<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/ProdutoController.php';

AuthGuard::requireMaster();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: produtos.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT);
$idCategoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);
$nome = trim($_POST['nome'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$foto = trim($_POST['foto'] ?? '');
$precoTexto = trim($_POST['preco_venda'] ?? '');
$ativo = filter_input(INPUT_POST, 'ativo', FILTER_VALIDATE_INT);

// Normalização do preço
if (str_contains($precoTexto, ',') && str_contains($precoTexto, '.')) {
    // Formato brasileiro: 1.250,90
    $precoTexto = str_replace('.', '', $precoTexto);
    $precoTexto = str_replace(',', '.', $precoTexto);
} elseif (str_contains($precoTexto, ',')) {
    // Formato brasileiro: 25,90
    $precoTexto = str_replace(',', '.', $precoTexto);
}
// Se tiver somente ponto, mantém o formato decimal: 25.90

if (
    !$id ||
    !$idCategoria ||
    $nome === '' ||
    !is_numeric($precoTexto) ||
    (float) $precoTexto <= 0 ||
    !in_array($ativo, [0, 1], true) ||
    mb_strlen($nome) > 120 ||
    mb_strlen($descricao) > 65535 ||
    mb_strlen($foto) > 255
) {
    header('Location: produtos.php?erro=dados');
    exit;
}

$controller = new ProdutoController();

try {
    if (!$controller->buscarPorId($id)) {
        header('Location: produtos.php?erro=nao_encontrado');
        exit;
    }

    $atualizado = $controller->atualizar(
        $id,
        $idCategoria,
        $nome,
        $descricao,
        $foto,
        (float) $precoTexto,
        $ativo
    );

    header('Location: produtos.php?' . ($atualizado ? 'sucesso=editado' : 'erro=atualizacao'));
    exit;

} catch (Throwable $e) {
    error_log('Erro ao atualizar produto: ' . $e->getMessage());
    header('Location: produtos.php?erro=atualizacao');
    exit;
}