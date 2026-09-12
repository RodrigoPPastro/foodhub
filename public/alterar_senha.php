<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Services/PasswordService.php';
require_once __DIR__ . '/../src/Models/Usuario.php';

AuthGuard::requireComum(); // só o próprio usuário comum altera a própria senha

$mensagem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha  = $_POST['nova_senha'] ?? '';
    $confirmar  = $_POST['confirmar_senha'] ?? '';

    $usuario = Usuario::buscarPorId(SessionService::usuarioIdAtual());

    if (!PasswordService::verify($senhaAtual, $usuario['senha_hash'])) {
        $mensagem = 'Senha atual incorreta.';
    } elseif (strlen($novaSenha) !== 8 || !ctype_alpha($novaSenha)) {
        $mensagem = 'A nova senha deve ter exatamente 8 caracteres alfabéticos.';
    } elseif ($novaSenha !== $confirmar) {
        $mensagem = 'A confirmação não confere com a nova senha.';
    } else {
        Usuario::atualizarSenha($usuario['id_usuario'], PasswordService::hash($novaSenha));
        $mensagem = 'Senha alterada com sucesso.';
    }
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
        <p><?= htmlspecialchars($mensagem) ?></p>
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
