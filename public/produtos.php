<?php

require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/ProdutoController.php';
require_once __DIR__ . '/../src/Controllers/CategoriaController.php';

AuthGuard::requireMaster();

$produtoController = new ProdutoController();
$categoriaController = new CategoriaController();

$produtos = $produtoController->listar();
$categorias = $categoriaController->listar();

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>FOOD HUB - Produtos</title>
</head>

<body>

    <h1>Gerenciamento de Produtos</h1>

    <?php if (isset($_GET['sucesso'])): ?>
        <p style="color: green;">
            Produto cadastrado com sucesso!
        </p>
    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>
        <p style="color: red;">
            Não foi possível cadastrar o produto. Verifique os dados.
        </p>
    <?php endif; ?>

    <h2>Cadastrar Produto</h2>

    <?php if (count($categorias) === 0): ?>

        <p>Cadastre uma categoria antes de cadastrar produtos.</p>

    <?php else: ?>

        <form method="POST" action="produtos_process.php">

            <label>Nome do produto:</label>
            <input type="text" name="nome" maxlength="120" required>

            <br><br>

            <label>Categoria:</label>

            <select name="id_categoria" required>
                <option value="">Selecione uma categoria</option>

                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= (int) $categoria['id_categoria'] ?>">
                        <?= htmlspecialchars($categoria['nome'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>

            </select>

            <br><br>

            <label>Descrição:</label>
            <br>
            <textarea name="descricao" rows="4" cols="40"></textarea>

            <br><br>

            <label>Foto (caminho ou URL):</label>
            <input type="text" name="foto" maxlength="255">

            <br><br>

            <label>Preço de venda (R$):</label>
            <input type="text" name="preco_venda"
                   placeholder="Ex.: 25,90" required>

            <br><br>

            <label>Status:</label>
            <select name="ativo" required>
                <option value="1">Ativo</option>
                <option value="0">Inativo</option>
            </select>

            <br><br>

            <button type="submit">Cadastrar produto</button>

        </form>

    <?php endif; ?>

    <hr>

    <h2>Produtos cadastrados</h2>

    <table border="1" cellpadding="8">

        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Categoria</th>
            <th>Preço</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>

        <?php foreach ($produtos as $produto): ?>

            <tr>

                <td><?= (int) $produto['id_produto'] ?></td>

                <td>
                    <?= htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8') ?>
                </td>

                <td>
                    <?= htmlspecialchars($produto['nome_categoria'], ENT_QUOTES, 'UTF-8') ?>
                </td>

                <td>
                    R$ <?= number_format((float) $produto['preco_venda'], 2, ',', '.') ?>
                </td>

                <td>
                    <?= $produto['ativo'] ? 'Ativo' : 'Inativo' ?>
                </td>

                <td>
                    <a href="produto_editar.php?id=<?= (int) $produto['id_produto'] ?>">
                        Editar
                    </a>

                    |

                    <a href="produto_excluir.php?id=<?= (int) $produto['id_produto'] ?>"
                       onclick="return confirm('Deseja realmente excluir este produto?')">
                        Excluir
                    </a>
                </td>

            </tr>

        <?php endforeach; ?>

    </table>

    <br>

    <a href="Categorias.php">Voltar para categorias</a>

</body>
</html>