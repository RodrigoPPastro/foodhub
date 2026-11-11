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

    <meta name="theme-color" content="#b3161b">

    <title>Recuperação de Senha - FOOD HUB</title>

    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --fh-bg: #f6efe0;
            --fh-card: #fffaf0;
            --fh-text: #1a1a1a;
            --fh-muted: #6b5f57;
            --fh-border: #e3d6b8;
            --fh-red: #b3161b;
            --fh-red-dark: #8f1015;
            --fh-gold: #c99a2e;
        }

        [data-theme="dark"] {
            --fh-bg: #141110;
            --fh-card: #1f1a18;
            --fh-text: #f3ebdd;
            --fh-muted: #b8aba0;
            --fh-border: #3a302b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            margin: 0;
            background: var(--fh-bg);
            color: var(--fh-text);
            font-family: 'Source Sans 3', sans-serif;
            transition: background .2s, color .2s;
        }

        .fh-card {
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            background: var(--fh-card);
            border: 1px solid var(--fh-border);
            border-radius: 12px;
            box-shadow: 0 12px 35px rgba(33, 25, 20, .10);
        }

        /* Cabeçalho no padrão visual do Cadastro */

        .fh-topo {
            position: relative;
            display: flex;
            align-items: center;
            gap: 18px;
            min-height: 125px;
            padding: 20px 24px;
            color: #fffaf0;
            background:
                radial-gradient(circle at 90% 10%, rgba(201, 154, 46, .20), transparent 35%),
                linear-gradient(115deg, #8f1015, #b3161b);
            border-bottom: 4px solid #c99a2e;
        }

        .fh-logo {
            display: block;
            flex: 0 0 auto;
            width: 105px;
            max-height: 75px;
            object-fit: contain;
        }

        .fh-topo-texto {
            min-width: 0;
        }

        .fh-etiqueta {
            display: inline-block;
            margin-bottom: 6px;
            padding: 4px 11px;
            background: rgba(255, 250, 240, .12);
            border: 1px solid rgba(255, 250, 240, .4);
            border-radius: 20px;
            color: #fffaf0;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .fh-title {
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            font-size: 1.55rem;
            line-height: 1.2;
            margin: 0 0 5px;
            color: #fffaf0;
        }

        .fh-sub {
            margin: 0;
            color: #fffaf0;
            opacity: .88;
            font-size: 13px;
            line-height: 1.4;
        }

        /* Conteúdo do formulário */

        .fh-conteudo {
            padding: 28px 32px 28px;
        }

        .fh-question-box {
            padding: 15px 16px;
            margin-bottom: 20px;
            background: var(--fh-bg);
            border: 1px solid var(--fh-border);
            border-left: 4px solid var(--fh-red);
            border-radius: 7px;
            color: var(--fh-text);
            font-weight: 600;
            line-height: 1.5;
        }

        .form-label {
            display: block;
            margin-bottom: 7px;
            color: var(--fh-text);
            font-weight: 700;
        }

        .form-control {
            min-height: 46px;
            background: var(--fh-bg);
            color: var(--fh-text);
            border: 1px solid var(--fh-border);
            border-radius: 7px;
            padding: .7rem .9rem;
        }

        .form-control:focus {
            background: var(--fh-bg);
            color: var(--fh-text);
            border-color: var(--fh-gold);
            box-shadow: 0 0 0 .2rem rgba(201, 154, 46, .22);
        }

        .form-control::placeholder {
            color: var(--fh-muted);
            opacity: .75;
        }

        .btn-fh {
            min-height: 47px;
            background: var(--fh-red);
            border: 1px solid var(--fh-red);
            border-radius: 7px;
            color: #fff;
            font-family: 'Oswald', sans-serif;
            font-size: 1.05rem;
            font-weight: 500;
            letter-spacing: .04em;
            padding: .7rem 1rem;
            transition: background .2s, transform .2s;
        }

        .btn-fh:hover,
        .btn-fh:focus {
            background: var(--fh-red-dark);
            border-color: var(--fh-red-dark);
            color: #fff;
            transform: translateY(-1px);
        }

        .fh-back {
            display: block;
            text-align: center;
            margin-top: 22px;
            color: var(--fh-muted);
            font-size: .95rem;
            text-decoration: none;
            transition: color .2s;
        }

        .fh-back:hover {
            color: var(--fh-red);
            text-decoration: underline;
        }

        .fh-theme {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 10;
            background: var(--fh-card);
            color: var(--fh-text);
            border: 1px solid var(--fh-border);
            border-radius: 7px;
            padding: .45rem .9rem;
            font-size: .9rem;
            cursor: pointer;
        }

        .fh-theme:hover {
            border-color: var(--fh-gold);
        }

        .alert {
            border-radius: 7px;
            font-size: .95rem;
        }

        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }

            .fh-topo {
                gap: 13px;
                min-height: 110px;
                padding: 17px 16px;
            }

            .fh-logo {
                width: 82px;
                max-height: 65px;
            }

            .fh-title {
                font-size: 1.3rem;
            }

            .fh-etiqueta {
                font-size: 9px;
                padding: 3px 9px;
            }

            .fh-sub {
                font-size: 12px;
            }

            .fh-conteudo {
                padding: 24px 20px;
            }

            .fh-theme {
                top: .65rem;
                right: .65rem;
                padding: .35rem .65rem;
                font-size: .8rem;
            }
        }

        @media (max-width: 350px) {
            .fh-topo {
                gap: 10px;
                padding: 15px 12px;
            }

            .fh-logo {
                width: 70px;
            }

            .fh-title {
                font-size: 1.15rem;
            }
        }
    </style>
</head>

<body>

    <button
        type="button"
        class="fh-theme"
        id="themeToggle"
        aria-label="Alternar tema claro e escuro"
    >
        Modo escuro
    </button>

    <main class="fh-card">

        <header class="fh-topo">

            <img
                src="/foodhub/public/assets/img/logo-foodhub.png"
                alt="FOOD HUB"
                class="fh-logo"
            >

            <div class="fh-topo-texto">

                
                <?php if ($etapa === 'pergunta' && $pendente !== null): ?>

                    <h1 class="fh-title">Verificação de segurança</h1>

                    <p class="fh-sub">
                        Responda à pergunta para continuar.
                    </p>

                <?php else: ?>

                    <h1 class="fh-title">Esqueci minha senha</h1>

                    <p class="fh-sub">
                        Vamos ajudar você a recuperar seu acesso.
                    </p>

                <?php endif; ?>

            </div>

        </header>

        <section class="fh-conteudo">

            <?php if ($etapa === 'pergunta' && $pendente !== null): ?>

                <?php if ($erro !== null): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars((string) $erro, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <p class="mb-3">
                    Responda à pergunta de segurança cadastrada na sua conta.
                </p>

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

                <?php if ($erro !== null): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars((string) $erro, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <p class="mb-4">
                    Informe seu login cadastrado para iniciar o processo de recuperação da senha.
                </p>

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
                        class="form-control mb-4"
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

        </section>

    </main>

    <script>
        (function () {
            var root = document.documentElement;
            var btn = document.getElementById('themeToggle');
            var saved = null;

            try {
                saved = localStorage.getItem('foodhub-theme');
            } catch (e) {}

            function apply(theme) {
                root.setAttribute('data-theme', theme);
                btn.textContent = theme === 'dark' ? 'Modo claro' : 'Modo escuro';
            }

            apply(saved === 'dark' ? 'dark' : 'light');

            btn.addEventListener('click', function () {
                var next = root.getAttribute('data-theme') === 'dark'
                    ? 'light'
                    : 'dark';

                apply(next);

                try {
                    localStorage.setItem('foodhub-theme', next);
                } catch (e) {}
            });
        })();
    </script>

</body>
</html>