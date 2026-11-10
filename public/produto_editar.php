<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/ProdutoController.php';
require_once __DIR__ . '/../src/Controllers/CategoriaController.php';

AuthGuard::requireMaster();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: produtos.php?erro=id');
    exit;
}

$produtoController = new ProdutoController();
$categoriaController = new CategoriaController();

try {
    $produto = $produtoController->buscarPorId($id);
    if (!$produto) {
        header('Location: produtos.php?erro=nao_encontrado');
        exit;
    }

    $categorias = $categoriaController->listar();
} catch (Throwable $e) {
    error_log('Erro ao carregar produto para edição: ' . $e->getMessage());
    header('Location: produtos.php?erro=consulta');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar produto - FoodHub</title>
</head>
<body>
    <h1>Editar produto</h1>

    <form method="POST" action="produto_editar_process.php">
        <input type="hidden" name="id_produto" value="<?= (int) $produto['id_produto'] ?>">

        <label for="nome">Nome do produto:</label>
        <input
            type="text"
            id="nome"
            name="nome"
            maxlength="120"
            value="<?= htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8') ?>"
            required
        >
        <br><br>

        <label for="id_categoria">Categoria:</label>
        <select id="id_categoria" name="id_categoria" required>
            <option value="">Selecione uma categoria</option>
            <?php foreach ($categorias as $categoria): ?>
                <option
                    value="<?= (int) $categoria['id_categoria'] ?>"
                    <?= (int) $produto['id_categoria'] === (int) $categoria['id_categoria'] ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($categoria['nome'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br><br>

        <label for="descricao">Descrição:</label><br>
        <textarea id="descricao" name="descricao" rows="4" cols="40"><?= htmlspecialchars($produto['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        <br><br>

        <label for="foto">Foto (caminho ou URL):</label>
        <input
            type="text"
            id="foto"
            name="foto"
            maxlength="255"
            value="<?= htmlspecialchars($produto['foto'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >
        <br><br>

        <label for="preco_venda">Preço de venda (R$):</label>
        <input
            type="text"
            id="preco_venda"
            name="preco_venda"
            value="<?= htmlspecialchars(number_format((float) $produto['preco_venda'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>"
            required
        >
        <br><br>

        <label for="ativo">Status:</label>
        <select id="ativo" name="ativo" required>
            <option value="1" <?= (int) $produto['ativo'] === 1 ? 'selected' : '' ?>>Ativo</option>
            <option value="0" <?= (int) $produto['ativo'] === 0 ? 'selected' : '' ?>>Inativo</option>
        </select>
        <br><br>

        <button type="submit">Salvar alterações</button>
        <a href="produtos.php">Cancelar</a>
    </form>
</body>
</html>
