<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/AuthGuard.php';

AuthGuard::requireMaster();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$erro = null;
$insumos = [];
$mensagem = $_SESSION['mensagem'] ?? null;

unset($_SESSION['mensagem']);

try {
    $pdo = Database::getConnection();

    $sql = "SELECT
                id_insumo,
                nome,
                unidade_medida,
                estoque_atual,
                estoque_minimo,
                custo_unitario,
                ativo
            FROM insumo
            ORDER BY nome";

    $stmt = $pdo->query($sql);
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Erro ao carregar estoque: ' . $e->getMessage());
    $erro = 'Não foi possível carregar os insumos. Verifique a estrutura do banco de dados.';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Controle de Estoque - FoodHub</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 25px;
            color: #333;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h1, h2 {
            color: #333;
        }

        .acoes {
            margin: 20px 0;
        }

        .botao {
            display: inline-block;
            padding: 11px 16px;
            margin: 5px;
            background: #198754;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .botao-secundario {
            background: #555;
        }

        .formulario {
            background: #f8f9fa;
            padding: 20px;
            margin: 25px 0;
            border-radius: 8px;
        }

        .campo {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #eee;
        }

        .baixo {
            color: #c0392b;
            font-weight: bold;
        }

        .normal {
            color: #198754;
        }

        .erro {
            background: #f8d7da;
            color: #842029;
            padding: 12px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .sucesso {
            background: #d1e7dd;
            color: #0f5132;
            padding: 12px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .vazio {
            padding: 20px;
            text-align: center;
        }

        @media (max-width: 700px) {
            .container {
                padding: 12px;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 7px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Controle de Estoque</h1>

    <p>Cadastro e acompanhamento dos insumos do FoodHub.</p>

    <div class="acoes">

        <a class="botao" href="ficha_tecnica.php">
            Ficha técnica
        </a>

        <a class="botao botao-secundario" href="modelo_bd.php">
            Modelo do banco de dados
        </a>

    </div>

    <?php if ($mensagem): ?>

        <div class="sucesso">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php endif; ?>

    <?php if ($erro): ?>

        <div class="erro">
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>

    <?php else: ?>

        <!-- CADASTRO DE INSUMOS -->

        <div class="formulario">

            <h2>Cadastrar novo insumo</h2>

            <form action="insumo_process.php" method="POST">

                <div class="campo">
                    <label for="nome">Nome do insumo:</label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="unidade_medida">Unidade de medida:</label>

                    <select
                        id="unidade_medida"
                        name="unidade_medida"
                        required
                    >
                        <option value="">Selecione</option>
                        <option value="kg">Quilograma (kg)</option>
                        <option value="g">Grama (g)</option>
                        <option value="L">Litro (L)</option>
                        <option value="ml">Mililitro (ml)</option>
                        <option value="unidade">Unidade</option>
                        <option value="pacote">Pacote</option>
                        <option value="caixa">Caixa</option>
                    </select>
                </div>

                <div class="campo">
                    <label for="estoque_atual">Estoque inicial:</label>

                    <input
                        type="number"
                        id="estoque_atual"
                        name="estoque_atual"
                        min="0"
                        step="0.001"
                        value="0"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="estoque_minimo">Estoque mínimo:</label>

                    <input
                        type="number"
                        id="estoque_minimo"
                        name="estoque_minimo"
                        min="0"
                        step="0.001"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="custo_unitario">Custo unitário (R$):</label>

                    <input
                        type="number"
                        id="custo_unitario"
                        name="custo_unitario"
                        min="0"
                        step="0.01"
                        required
                    >
                </div>

                <button type="submit" class="botao">
                    Cadastrar insumo
                </button>

            </form>

        </div>

        <!-- MOVIMENTAÇÃO DE ESTOQUE -->

        <div class="formulario">

            <h2>Registrar movimentação de estoque</h2>

            <?php if (empty($insumos)): ?>

                <p>Cadastre um insumo antes de registrar movimentações.</p>

            <?php else: ?>

                <form
                    action="movimento_estoque_process.php"
                    method="POST"
                >

                    <div class="campo">

                        <label for="id_insumo">Insumo:</label>

                        <select
                            name="id_insumo"
                            id="id_insumo"
                            required
                        >

                            <option value="">Selecione um insumo</option>

                            <?php foreach ($insumos as $insumo): ?>

                                <?php if ((int) $insumo['ativo'] === 1): ?>

                                    <option value="<?= (int) $insumo['id_insumo'] ?>">

                                        <?= htmlspecialchars(
                                            $insumo['nome'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </option>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="campo">

                        <label for="tipo">Tipo de movimentação:</label>

                        <select
                            name="tipo"
                            id="tipo"
                            required
                        >
                            <option value="">Selecione</option>
                            <option value="entrada">Entrada</option>
                            <option value="saida">Saída</option>
                            <option value="perda">Perda</option>
                        </select>

                    </div>

                    <div class="campo">

                        <label for="quantidade">Quantidade:</label>

                        <input
                            type="number"
                            name="quantidade"
                            id="quantidade"
                            min="0.001"
                            step="0.001"
                            required
                        >

                    </div>

                    <div class="campo">

                        <label for="motivo">Motivo:</label>

                        <input
                            type="text"
                            name="motivo"
                            id="motivo"
                            maxlength="255"
                            placeholder="Ex.: Compra de mercadoria"
                        >

                    </div>

                    <button type="submit" class="botao">
                        Registrar movimentação
                    </button>

                </form>

            <?php endif; ?>

        </div>

        <!-- LISTAGEM DE INSUMOS -->

        <h2>Insumos cadastrados</h2>

        <?php if (empty($insumos)): ?>

            <div class="vazio">
                Nenhum insumo cadastrado.
            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>
                        <th>Nome</th>
                        <th>Unidade</th>
                        <th>Estoque atual</th>
                        <th>Estoque mínimo</th>
                        <th>Custo unitário</th>
                        <th>Situação</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($insumos as $insumo): ?>

                        <?php

                        $estoqueBaixo =
                            (float) $insumo['estoque_atual']
                            <= (float) $insumo['estoque_minimo'];

                        ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $insumo['nome'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $insumo['unidade_medida'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $insumo['estoque_atual'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $insumo['estoque_minimo'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                R$ <?= number_format(
                                    (float) $insumo['custo_unitario'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <td class="<?= $estoqueBaixo ? 'baixo' : 'normal' ?>">

                                <?= $estoqueBaixo
                                    ? 'Estoque baixo'
                                    : 'Normal' ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
    const tipoMovimentacao = document.getElementById('tipo');
    const campoMotivo = document.getElementById('motivo');

    function atualizarMotivoObrigatorio() {

        if (!tipoMovimentacao || !campoMotivo) {
            return;
        }

        const perdaSelecionada =
            tipoMovimentacao.value === 'perda';

        campoMotivo.required = perdaSelecionada;

        campoMotivo.placeholder = perdaSelecionada
            ? 'Informe o motivo da perda'
            : 'Ex.: Compra de mercadoria';
    }

    if (tipoMovimentacao) {
        tipoMovimentacao.addEventListener(
            'change',
            atualizarMotivoObrigatorio
        );

        atualizarMotivoObrigatorio();
    }
</script>

</body>
</html>