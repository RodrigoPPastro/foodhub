<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/AuthGuard.php';

AuthGuard::requireMaster();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /foodhub/public/estoque.php');
    exit;
}

try {
    $nome = trim($_POST['nome'] ?? '');
    $unidade = trim($_POST['unidade_medida'] ?? '');

    $estoqueAtual = str_replace(',', '.', trim($_POST['estoque_atual'] ?? ''));
    $estoqueMinimo = str_replace(',', '.', trim($_POST['estoque_minimo'] ?? ''));
    $custoUnitario = str_replace(',', '.', trim($_POST['custo_unitario'] ?? ''));

    if ($nome === '' || $unidade === '') {
        throw new Exception('Informe o nome e a unidade de medida.');
    }

    if (
        !is_numeric($estoqueAtual) ||
        !is_numeric($estoqueMinimo) ||
        !is_numeric($custoUnitario)
    ) {
        throw new Exception('Informe valores numéricos válidos.');
    }

    $estoqueAtual = (float) $estoqueAtual;
    $estoqueMinimo = (float) $estoqueMinimo;
    $custoUnitario = (float) $custoUnitario;

    if (
        $estoqueAtual < 0 ||
        $estoqueMinimo < 0 ||
        $custoUnitario < 0
    ) {
        throw new Exception('Os valores não podem ser negativos.');
    }

    $pdo = Database::getConnection();

    $sql = "INSERT INTO insumo
            (nome, unidade_medida, estoque_atual,
             estoque_minimo, custo_unitario, ativo, criado_em)
            VALUES
            (:nome, :unidade, :estoque_atual,
             :estoque_minimo, :custo_unitario, 1, NOW())";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':nome' => $nome,
        ':unidade' => $unidade,
        ':estoque_atual' => $estoqueAtual,
        ':estoque_minimo' => $estoqueMinimo,
        ':custo_unitario' => $custoUnitario
    ]);

    $_SESSION['mensagem'] = 'Insumo cadastrado com sucesso!';

} catch (Throwable $e) {

    error_log('Erro ao cadastrar insumo: ' . $e->getMessage());

    $_SESSION['mensagem'] = 'Erro ao cadastrar insumo: ' . $e->getMessage();
}

header('Location: /foodhub/public/estoque.php');
exit;