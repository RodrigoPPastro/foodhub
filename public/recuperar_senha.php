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
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recuperação de Senha - FOOD HUB</title>

    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --fh-bg: #F6EFE0;        /* creme */
            --fh-card: #FFFBF2;
            --fh-text: #1A1412;
            --fh-muted: #6B5F57;
            --fh-border: #E2D6BF;
            --fh-red: #B3261E;
            --fh-red-dark: #8F1D17;
            --fh-gold: #C9A227;
        }
        [data-theme="dark"] {
            --fh-bg: #141110;
            --fh-card: #1F1A18;
            --fh-text: #F3EBDD;
            --fh-muted: #B8ABA0;
            --fh-border: #3A302B;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--fh-bg);
            color: var(--fh-text);
            font-family: 'Source Sans 3', sans-serif;
        }

        .fh-card {
            width: 100%;
            max-width: 420px;
            padding: 2.25rem 2rem;
            background: var(--fh-card);
            border: 1px solid var(--fh-border);
            border-top: 4px solid var(--fh-red);
            border-radius: .5rem;
        }

        .fh-logo { display: block; max-width: 180px; margin: 0 auto 1.5rem; }

        .fh-title {
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            font-size: 1.6rem;
            text-align: center;
            margin-bottom: .35rem;
        }
        .fh-sub { text-align: center; color: var(--fh-muted); margin-bottom: 1.5rem; }

        .fh-question-box {
            padding: .85rem 1rem;
            margin-bottom: 1rem;
            background: var(--fh-bg);
            border: 1px solid var(--fh-border);
            border-left: 4px solid var(--fh-red);
            border-radius: .35rem;
            font-weight: 600;
        }

        .form-label { font-weight: 600; }

        .form-control {
            background: var(--fh-bg);
            color: var(--fh-text);
            border-color: var(--fh-border);
            padding: .7rem .9rem;
        }
        .form-control:focus {
            background: var(--fh-bg);
            color: var(--fh-text);
            border-color: var(--fh-gold);
            box-shadow: 0 0 0 .2rem rgba(201, 162, 39, .3);
        }

        .btn-fh {
            background: var(--fh-red);
            border-color: var(--fh-red);
            color: #fff;
            font-family: 'Oswald', sans-serif;
            font-weight: 500;
            letter-spacing: .04em;
            padding: .7rem;
        }
        .btn-fh:hover, .btn-fh:focus { background: var(--fh-red-dark); border-color: var(--fh-red-dark); color: #fff; }

        .fh-back { display: block; text-align: center; margin-top: 1.25rem; color: var(--fh-muted); font-size: .95rem; }
        .fh-back:hover { color: var(--fh-red); }

        .fh-theme {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: var(--fh-card);
            color: var(--fh-text);
            border: 1px solid var(--fh-border);
            border-radius: .35rem;
            padding: .35rem .9rem;
            font-size: .9rem;
        }
    </style>
</head>

<body>

    <button type="button" class="fh-theme" id="themeToggle" aria-label="Alternar tema claro/escuro">Modo escuro</button>

    <main class="fh-card">

        <!-- Use o mesmo caminho da logo que o login.php já usa -->
        <img src="/foodhub/public/assets/img/logo-foodhub.png" alt="FOOD HUB" class="fh-logo">

        <?php if ($etapa === 'pergunta' && $pendente !== null): ?>

            <h1 class="fh-title">Recuperação de Senha</h1>

            <p class="fh-sub">
                Responda à pergunta de segurança para continuar.
            </p>

            <?php if ($erro !== null): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars((string) $erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
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

                <div class="fh-question-box">
                    <?= htmlspecialchars(
                        $pendente['pergunta'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

                <label for="resposta" class="form-label">
                    Sua resposta
                </label>

                <input
                    type="text"
                    id="resposta"
                    name="resposta"
                    class="form-control mb-3"
                    required
                    autocomplete="off"
                    autofocus
                >

                <button type="submit" class="btn btn-fh w-100">
                    Validar resposta
                </button>

            </form>

            <a href="/foodhub/public/login.php" class="fh-back">
                Voltar para o login
            </a>

        <?php else: ?>

            <h1 class="fh-title">Esqueci minha senha</h1>

            <p class="fh-sub">
                Informe seu login para iniciar a recuperação da senha.
            </p>

            <?php if ($erro !== null): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars((string) $erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
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

                <label for="login" class="form-label">
                    Login
                </label>

                <input
                    type="text"
                    id="login"
                    name="login"
                    class="form-control mb-3"
                    required
                    autocomplete="username"
                    autofocus
                >

                <button type="submit" class="btn btn-fh w-100">
                    Continuar
                </button>

            </form>

            <a href="/foodhub/public/login.php" class="fh-back">
                Voltar para o login
            </a>

        <?php endif; ?>

    </main>

    <script>
        (function () {
            var root = document.documentElement;
            var btn = document.getElementById('themeToggle');
            var saved = null;
            try { saved = localStorage.getItem('foodhub-theme'); } catch (e) {}

            function apply(theme) {
                root.setAttribute('data-theme', theme);
                btn.textContent = theme === 'dark' ? 'Modo claro' : 'Modo escuro';
            }
            apply(saved === 'dark' ? 'dark' : 'light');

            btn.addEventListener('click', function () {
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                apply(next);
                try { localStorage.setItem('foodhub-theme', next); } catch (e) {}
            });
        })();
    </script>

</body>
</html>
