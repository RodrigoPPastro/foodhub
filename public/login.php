<?php
require_once __DIR__ . '/../src/Services/SessionService.php';
SessionService::start();

// Se já está logado, não faz sentido ver a tela de login de novo.
if (SessionService::estaAutenticado()) {
    header('Location: /public/dashboard.php');
    exit;
}

$erro = $_GET['erro'] ?? null;

$mensagemErro = null;
if ($erro === 'credenciais') {
    $mensagemErro = 'Login ou senha inválidos.';
} elseif ($erro === '2fa_bloqueado') {
    $mensagemErro = '3 tentativas sem sucesso! Favor realizar login novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FOOD HUB — Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/variables.css?v=2">
    <link rel="stylesheet" href="assets/css/custom.css?v=2">
</head>
<body>
    <main class="fh-page">
        <button type="button" id="themeToggle" class="fh-theme-toggle" aria-label="Alternar tema claro/escuro">
            <svg class="fh-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>
            </svg>
            <svg class="fh-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            </svg>
        </button>

        <div class="fh-card">
            <div class="fh-logo-badge">
                <img src="assets/img/logo.jpg" alt="FOOD HUB">
            </div>

            <h1>Entrar</h1>
            <p class="fh-subtitle">Acesse com seu login e senha cadastrados.</p>

            <?php if ($mensagemErro): ?>
                <div class="alert alert-danger py-2" role="alert">
                    <?= htmlspecialchars($mensagemErro) ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" action="login_process.php" method="post" novalidate>
                <div class="mb-3">
                    <label for="login" class="fh-form-label">Login</label>
                    <input
                        type="text"
                        class="form-control"
                        id="login"
                        name="login"
                        maxlength="6"
                        pattern="[A-Za-z]{6}"
                        autocomplete="username"
                        required
                    >
                    <div class="invalid-feedback">O login deve ter exatamente 6 letras.</div>
                </div>

                <div class="mb-3">
                    <label for="senha" class="fh-form-label">Senha</label>
                    <input
                        type="password"
                        class="form-control"
                        id="senha"
                        name="senha"
                        autocomplete="current-password"
                        required
                    >
                    <div class="invalid-feedback">Informe sua senha.</div>
                </div>

                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary w-100">ENTRAR</button>
                    <button type="reset" class="btn btn-outline-secondary">LIMPAR</button>
                </div>

                <div class="fh-links">
                    <a href="cadastro.php">Cadastre-se</a>
                    <a href="recuperar_senha.php">Esqueci minha senha</a>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js?v=5"></script>
</body>
</html>
