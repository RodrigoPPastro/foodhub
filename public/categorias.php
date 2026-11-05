<?php

require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/CategoriaController.php';

AuthGuard::requireMaster();

$controller = new CategoriaController();
$categorias = $controller->listar();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>FOOD HUB — Categorias</title>
</head>
<body>

    <h1>Gerenciamento de Categorias</h1>
<?php if (isset($_GET['sucesso'])): ?>
    <p style="color: green;">Categoria cadastrada com sucesso!</p>
<?php endif; ?>

<?php if (isset($_GET['erro']) && $_GET['erro'] === 'cadastro'): ?>
    <p style="color: red;">Esta categoria já existe ou ocorreu um erro no cadastro.</p>
<?php endif; ?>

<?php if (isset($_GET['erro']) && $_GET['erro'] === 'nome'): ?>
    <p style="color: red;">Informe o nome da categoria.</p>
<?php endif; ?>

<?php if (isset($_GET['erro']) && $_GET['erro'] === 'tamanho'): ?>
    <p style="color: red;">Os campos ultrapassaram o limite permitido.</p>
<?php endif; ?>
    <form method="post" action="categorias_process.php">

        <label>Nome da categoria:</label>
        <input type="text" name="nome" maxlength="80" required>

        <label>Descrição:</label>
        <input type="text" name="descricao" maxlength="255">

        <button type="submit" name="acao" value="cadastrar">
            Cadastrar categoria
        </button>

    </form>

    <h2>Categorias cadastradas</h2>

    <table border="1" cellpadding="8">

        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Descrição</th>
            <th>Acoes</th>
        </tr>

        <?php foreach ($categorias as $categoria): ?>

        <tr>
            <td><?= (int) $categoria['id_categoria'] ?></td>

            <td>
                <?= htmlspecialchars($categoria['nome'], ENT_QUOTES, 'UTF-8') ?>
            </td>

            <td>
                <?= htmlspecialchars($categoria['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td>
    <a href="categoria_editar.php?id=<?= (int) $categoria['id_categoria'] ?>">
        Editar
    </a>

    |

    <a href="categoria_excluir.php?id=<?= (int) $categoria['id_categoria'] ?>"
       onclick="return confirm('Deseja realmente excluir esta categoria?')">
        Excluir
    </a>
</td>
        </tr>

        <?php endforeach; ?>

    </table>

</body>
</html>