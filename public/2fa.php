<?php
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Services/TwoFactorService.php';

SessionService::start();

if (!TwoFactorService::existePendente()) {
    header('Location: /foodhub/public/login.php');
    exit;
}

$pergunta = TwoFactorService::perguntaAtual();
$erro = $_GET['erro'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>FOOD HUB — Verificação</title>
</head>
<body>
    <h1>Segundo Fator de Autenticação</h1>

    <?php if ($erro === 'invalida'): ?>
        <p style="color:red">Resposta incorreta. Tente novamente.</p>
    <?php endif; ?>

    <form action="2fa_process.php" method="post">
        <label><?= htmlspecialchars($pergunta) ?></label>
        <input type="text" name="resposta" required>
        <button type="submit">CONFIRMAR</button>
    </form>
</body>
</html>
