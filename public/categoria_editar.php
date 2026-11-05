<?php

require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/CategoriaController.php';

AuthGuard::requireMaster();

// Recebe o ID da categoria pela URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('ID da categoria inválido.');
}

// Busca a categoria no banco de dados
$controller = new CategoriaController();

try {
    $categoria = $controller->buscarPorId($id);
} catch (Throwable $e) {
    die('Erro ao buscar categoria: ' . htmlspecialchars($e->getMessage()));
}

if (!$categoria) {
    die('Categoria não encontrada. ID informado: ' . (int) $id);
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Editar Categoria - FOOD HUB</title>
</head>

<body>

    <h1>Editar Categoria</h1>

    <form method="POST" action="categoria_editar_process.php">

        <input
            type="hidden"
            name="id"
            value="<?= (int) $categoria['id_categoria'] ?>"
        >

        <label for="nome">Nome da categoria:</label>

        <input
            type="text"
            id="nome"
            name="nome"
            maxlength="80"
            value="<?= htmlspecialchars($categoria['nome'], ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <br><br>

        <label for="descricao">Descrição:</label>

        <input
            type="text"
            id="descricao"
            name="descricao"
            maxlength="255"
            value="<?= htmlspecialchars($categoria['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >

        <br><br>

        <button type="submit">Salvar alterações</button>

    </form>

    <br>

    <a href="Categorias.php">Voltar para categorias</a>

</body>
</html>