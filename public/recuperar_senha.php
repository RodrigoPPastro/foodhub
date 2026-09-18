<?php

require_once __DIR__ . '/../src/Services/SessionService.php';

SessionService::start();

if (SessionService::estaAutenticado()) {
    header('Location: /foodhub/public/dashboard.php');
    exit;
}

$etapa = $_GET['etapa'] ?? 'login';
$erro = SessionService::getFlash('recuperacao_erro');

$pendente = SessionService::dadosRecuperacaoPendente();

if ($etapa === 'pergunta' && $pendente === null) {
    header('Location: /foodhub/public/recuperar_senha.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recuperação de Senha - FOOD HUB</title>
</head>

<body>

    <main>

        <?php if ($etapa === 'pergunta' && $pendente !== null): ?>

            <h1>Recuperação de Senha</h1>

            <p>
                Responda à pergunta de segurança para continuar.
            </p>

            <?php if ($erro !== null): ?>
                <p>
                    <?= htmlspecialchars((string) $erro, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <form
                action="/foodhub/public/recuperar_senha_process.php"
                method="POST"
            >

                <input
                    type="hidden"
                    name="acao"
                    value="validar_pergunta"
                >

                <fieldset>
                    <legend>Pergunta de segurança</legend>

                    <p>
                        <?= htmlspecialchars(
                            $pendente['pergunta'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <label for="resposta">
                        Resposta:
                    </label>

                    <input
                        type="text"
                        id="resposta"
                        name="resposta"
                        required
                        autocomplete="off"
                    >

                </fieldset>

                <button type="submit">
                    Validar resposta
                </button>

            </form>

            <p>
                <a href="/foodhub/public/login.php">
                    Voltar para o login
                </a>
            </p>

        <?php else: ?>

            <h1>Esqueci minha senha</h1>

            <p>
                Informe seu login para iniciar a recuperação da senha.
            </p>

            <?php if ($erro !== null): ?>
                <p>
                    <?= htmlspecialchars((string) $erro, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <form
                action="/foodhub/public/recuperar_senha_process.php"
                method="POST"
            >

                <input
                    type="hidden"
                    name="acao"
                    value="iniciar"
                >

                <label for="login">
                    Login:
                </label>

                <input
                    type="text"
                    id="login"
                    name="login"
                    required
                    autocomplete="username"
                >

                <button type="submit">
                    Continuar
                </button>

            </form>

            <p>
                <a href="/foodhub/public/login.php">
                    Voltar para o login
                </a>
            </p>

        <?php endif; ?>

    </main>

</body>
</html>