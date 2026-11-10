<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/AuthGuard.php';

AuthGuard::requireMaster();

function voltarFicha(int $idProduto, string $mensagem): void
{
    header(
        'Location: ficha_tecnica.php?produto=' . $idProduto .
        '&msg=' . urlencode($mensagem)
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ficha_tecnica.php');
    exit;
}

$idProduto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT);
$idInsumo = filter_input(INPUT_POST, 'id_insumo', FILTER_VALIDATE_INT);
$acao = $_POST['acao'] ?? 'salvar';

if (!$idProduto || !$idInsumo) {
    voltarFicha((int)($idProduto ?: 0), 'Produto ou insumo inválido.');
}

try {
    $pdo = Database::getConnection();

    // Verifica produto
    $stmt = $pdo->prepare(
        "SELECT id_produto FROM produto WHERE id_produto = ?"
    );
    $stmt->execute([$idProduto]);

    if (!$stmt->fetch()) {
        throw new Exception('Produto não encontrado.');
    }

    // Verifica insumo
    $stmt = $pdo->prepare(
        "SELECT id_insumo FROM insumo WHERE id_insumo = ?"
    );
    $stmt->execute([$idInsumo]);

    if (!$stmt->fetch()) {
        throw new Exception('Insumo não encontrado.');
    }

    if ($acao === 'remover') {

        $stmt = $pdo->prepare(
            "DELETE FROM produto_insumo
             WHERE id_produto = ?
             AND id_insumo = ?"
        );

        $stmt->execute([$idProduto, $idInsumo]);

        voltarFicha(
            $idProduto,
            $stmt->rowCount() > 0
                ? 'Ingrediente removido com sucesso!'
                : 'Ingrediente não encontrado na ficha.'
        );
    }

    if ($acao !== 'salvar') {
        throw new Exception('Ação inválida.');
    }

    $quantidade = str_replace(
        ',',
        '.',
        trim($_POST['quantidade_utilizada'] ?? '')
    );

    if (!is_numeric($quantidade) || (float)$quantidade <= 0) {
        throw new Exception('Informe uma quantidade válida.');
    }

    // Atualiza se já existe; cadastra se ainda não existe.
    $stmt = $pdo->prepare(
        "INSERT INTO produto_insumo
            (id_produto, id_insumo, quantidade_utilizada)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            quantidade_utilizada = VALUES(quantidade_utilizada)"
    );

    $stmt->execute([
        $idProduto,
        $idInsumo,
        (float)$quantidade
    ]);

    voltarFicha($idProduto, 'Ficha técnica salva com sucesso!');

} catch (Throwable $e) {

    error_log(
        'Erro em ficha_tecnica_process.php: ' .
        $e->getMessage()
    );

    voltarFicha(
        $idProduto,
        'Erro ao salvar: ' . $e->getMessage()
    );
}