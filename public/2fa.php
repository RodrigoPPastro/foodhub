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
<html lang="pt-br" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FOOD HUB — Verificação</title>

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

        .fh-question { font-weight: 600; margin-bottom: .5rem; }

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

        <h1 class="fh-title">Confirme sua identidade</h1>
        <p class="fh-sub">Responda à pergunta de segurança para entrar.</p>

        <?php if ($erro === 'invalida'): ?>
            <div class="alert alert-danger" role="alert">
                Resposta incorreta. Confira e tente novamente.
            </div>
        <?php endif; ?>

        <form action="2fa_process.php" method="post">
            <label for="resposta" class="fh-question"><?= htmlspecialchars($pergunta) ?></label>
            <input type="text" id="resposta" name="resposta" class="form-control mb-3"
                   autocomplete="off" autofocus required>
            <button type="submit" class="btn btn-fh w-100">CONFIRMAR</button>
        </form>

        <a href="login.php" class="fh-back">Voltar ao login</a>
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
