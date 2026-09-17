<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Controllers/UsuarioController.php';

AuthGuard::requireComum();

$mensagem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = UsuarioController::alterarSenha(
        (int) SessionService::usuarioIdAtual(),
        $_POST['senha_atual'] ?? '',
        $_POST['nova_senha'] ?? '',
        $_POST['confirmar_senha'] ?? ''
    );

    $mensagem = $resultado['mensagem'];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>FOOD HUB — Alterar Senha</title>
</head>
<body>
    <h1>Alterar Senha</h1>

    <?php if ($mensagem): ?>
        <p><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Senha atual</label>
        <input type="password" name="senha_atual" required>

        <label>Nova senha (8 letras)</label>
        <input type="password" name="nova_senha" maxlength="8" required>

        <label>Confirmar nova senha</label>
        <input type="password" name="confirmar_senha" maxlength="8" required>

        <button type="submit">Salvar</button>
    </form>
</body>
</html>
