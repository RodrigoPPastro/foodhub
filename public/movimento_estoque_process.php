<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/AuthGuard.php';

AuthGuard::requireMaster();

$pdo = Database::getConnection();

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: estoque.php');
        exit;
    }

    $id_insumo = filter_input(INPUT_POST, 'id_insumo', FILTER_VALIDATE_INT);
    $tipo = strtolower(trim($_POST['tipo'] ?? ''));
    $quantidadeTexto = trim($_POST['quantidade'] ?? '');
    $motivo = trim($_POST['motivo'] ?? '');

    $quantidadeTexto = str_replace(',', '.', $quantidadeTexto);

    if (
        !$id_insumo ||
        !is_numeric($quantidadeTexto) ||
        (float)$quantidadeTexto <= 0
    ) {
        throw new Exception('Insumo ou quantidade inválida.');
    }

    if (!in_array($tipo, ['entrada', 'saida', 'perda'], true)) {
        throw new Exception('Tipo de movimentação inválido.');
    }

    if ($tipo === 'perda' && $motivo === '') {
        throw new Exception('Informe o motivo da perda.');
    }

    $id_usuario = SessionService::usuarioIdAtual();

    if (!$id_usuario) {
        throw new Exception('Usuário não identificado.');
    }

    $quantidade = (float)$quantidadeTexto;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT estoque_atual
         FROM insumo
         WHERE id_insumo = ?
         FOR UPDATE"
    );

    $stmt->execute([$id_insumo]);
    $insumo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$insumo) {
        throw new Exception('Insumo não encontrado.');
    }

    $estoque_atual = (float)$insumo['estoque_atual'];

    if (
        in_array($tipo, ['saida', 'perda'], true) &&
        $quantidade > $estoque_atual
    ) {
        throw new Exception('Estoque insuficiente.');
    }

    if ($tipo === 'entrada') {
        $novo_estoque = $estoque_atual + $quantidade;
    } else {
        $novo_estoque = $estoque_atual - $quantidade;
    }

    $stmt = $pdo->prepare(
        "UPDATE insumo
         SET estoque_atual = ?
         WHERE id_insumo = ?"
    );

    $stmt->execute([$novo_estoque, $id_insumo]);

    $stmt = $pdo->prepare(
        "INSERT INTO movimento_estoque
        (id_insumo, id_usuario, tipo, quantidade, data_hora, motivo)
        VALUES (?, ?, ?, ?, NOW(), ?)"
    );

    $stmt->execute([
        $id_insumo,
        $id_usuario,
        $tipo,
        $quantidade,
        $motivo
    ]);

    $pdo->commit();

    header('Location: /foodhub/public/estoque.php?sucesso=movimentacao');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro movimentação: ' . $e->getMessage());

    die('Erro ao registrar movimentação: ' .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}