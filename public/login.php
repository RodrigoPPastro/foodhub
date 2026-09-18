<?php
require_once __DIR__ . '/../src/Services/SessionService.php';
SessionService::start();

// Se já está logado, não faz sentido ver a tela de login de novo.
if (SessionService::estaAutenticado()) {
    header('Location: /foodhub/public/dashboard.php'); // ajustar quando a Dandara criar o dashboard
    exit;
}

$erro = $_GET['erro'] ?? null;
?>
<!-- NOTA PRA DANDARA: isto é só o esqueleto funcional (RF03).
     Estilizar com a identidade visual / framework CSS do grupo,
     mantendo os "name" dos campos e o método POST para login_process.php. -->
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>FOOD HUB — Login</title>
</head>
<body>
    <h1>Entrar</h1>

    <?php if ($erro === 'credenciais'): ?>
        <p style="color:red">Login ou senha inválidos.</p>
    <?php elseif ($erro === '2fa_bloqueado'): ?>
        <p style="color:red">3 tentativas sem sucesso! Favor realizar login novamente.</p>
    <?php endif; ?>

    <form action="login_process.php" method="post">
        <label>Login</label>
        <input type="text" name="login" maxlength="6" required>

        <label>Senha</label>
        <input type="password" name="senha" required>

        <button type="submit">ENTRAR</button>
        <button type="reset">LIMPAR</button>
    </form>

    <p>
        <a href="recuperar_senha.php">Esqueci minha senha</a>
    </p>

    <p>Novo por aqui? <a href="cadastro.php">Cadastre-se</a></p>
</body>
</html>
