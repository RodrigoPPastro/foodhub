<?php
require_once __DIR__ . '/../src/Services/SessionService.php';
SessionService::start();

// Se já está logado, não faz sentido ver a tela de login de novo.
if (SessionService::estaAutenticado()) {
    header('Location: dashboard.php');
    exit;
}

$erro = $_GET['erro'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6efe0">
    <title>FOOD HUB — Login</title>

    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --fh-vermelho: #b3161b;
            --fh-vermelho-escuro: #8f1015;
            --fh-dourado: #c99a2e;
            --fh-fundo: #f6efe0;
            --fh-cartao: #fffaf0;
            --fh-texto: #1a1a1a;
            --fh-texto-suave: #746b5e;
            --fh-borda: #e3d6b8;
            --fh-sombra: 0 18px 48px rgba(74, 42, 25, .12);
        }

        [data-bs-theme="dark"] {
            --fh-fundo: #141212;
            --fh-cartao: #211c1c;
            --fh-texto: #f3ead7;
            --fh-texto-suave: #c0b5a3;
            --fh-borda: #453838;
            --fh-sombra: 0 18px 48px rgba(0, 0, 0, .35);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Source Sans 3', sans-serif;
            color: var(--fh-texto);
            background:
                radial-gradient(circle at 12% 15%, rgba(201,154,46,.12), transparent 28%),
                radial-gradient(circle at 90% 85%, rgba(179,22,27,.09), transparent 30%),
                var(--fh-fundo);
        }

        .fh-titulo {
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
        }

        .fh-tema {
            position: fixed;
            top: 15px;
            right: 18px;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 13px;
            border: 1px solid var(--fh-borda);
            border-radius: 30px;
            background: var(--fh-cartao);
            color: var(--fh-texto);
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,.05);
            transition: .2s ease;
        }

        .fh-tema:hover {
            border-color: var(--fh-dourado);
            transform: translateY(-2px);
        }

        .fh-pagina {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 55px 16px 20px;
        }

        .fh-cartao {
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            background: var(--fh-cartao);
            border: 1px solid var(--fh-borda);
            border-radius: 16px;
            box-shadow: var(--fh-sombra);
            animation: fhEntrada .55s ease both;
        }

        .fh-faixa {
            height: 5px;
            background: linear-gradient(90deg, var(--fh-vermelho-escuro), var(--fh-vermelho), var(--fh-dourado));
        }

        .fh-conteudo {
            padding: 25px 34px 22px;
        }

        .fh-logo-area {
            text-align: center;
            margin-bottom: 16px;
        }

        .fh-logo {
            display: block;
            width: auto;
            max-width: 100%;
            max-height: 95px;
            margin: 0 auto;
        }

        [data-bs-theme="dark"] .fh-logo {
            filter: drop-shadow(0 0 3px rgba(243,234,215,.75));
        }

        .fh-etiqueta {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 11px;
            border-radius: 30px;
            background: rgba(179,22,27,.08);
            color: var(--fh-vermelho);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        [data-bs-theme="dark"] .fh-etiqueta {
            color: #edb3a9;
            background: rgba(179,22,27,.2);
        }

        .fh-cabecalho {
            text-align: center;
            margin-bottom: 19px;
        }

        .fh-cabecalho h1 {
            margin: 0 0 4px;
            font-size: 26px;
            color: var(--fh-texto);
        }

        .fh-cabecalho p {
            margin: 0;
            color: var(--fh-texto-suave);
            font-size: 13px;
        }

        .fh-campo {
            margin-bottom: 14px;
        }

        .fh-campo label {
            display: block;
            margin-bottom: 6px;
            color: var(--fh-texto);
            font-size: 14px;
            font-weight: 700;
        }

        .fh-campo .form-control {
            min-height: 44px;
            padding: 9px 12px;
            border: 1px solid var(--fh-borda);
            border-radius: 9px;
            background: var(--fh-fundo);
            color: var(--fh-texto);
            font-size: 15px;
            transition: .2s ease;
        }

        .fh-campo .form-control::placeholder {
            color: var(--fh-texto-suave);
            opacity: .75;
        }

        .fh-campo .form-control:focus {
            border-color: var(--fh-dourado);
            background: var(--fh-cartao);
            color: var(--fh-texto);
            box-shadow: 0 0 0 4px rgba(201,154,46,.16);
        }

        .fh-acoes {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-fh {
            min-height: 43px;
            border: 1px solid var(--fh-vermelho);
            border-radius: 9px;
            background: var(--fh-vermelho);
            color: #fff;
            font-family: 'Oswald', sans-serif;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: .8px;
            transition: .2s ease;
        }

        .btn-fh:hover,
        .btn-fh:focus {
            border-color: var(--fh-vermelho-escuro);
            background: var(--fh-vermelho-escuro);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 7px 17px rgba(143,16,21,.2);
        }

        .btn-fh:focus-visible,
        .btn-limpar:focus-visible,
        .fh-tema:focus-visible {
            outline: 3px solid var(--fh-dourado);
            outline-offset: 3px;
        }

        .btn-limpar {
            min-height: 43px;
            padding: 0 19px;
            border: 1px solid var(--fh-borda);
            border-radius: 9px;
            background: transparent;
            color: var(--fh-texto);
            font-family: 'Oswald', sans-serif;
            font-size: 14px;
            font-weight: 600;
            transition: .2s ease;
        }

        .btn-limpar:hover {
            border-color: var(--fh-dourado);
            background: rgba(201,154,46,.08);
            color: var(--fh-texto);
        }

        .fh-links {
            margin-top: 19px;
            padding-top: 15px;
            border-top: 1px solid var(--fh-borda);
            text-align: center;
            font-size: 14px;
        }

        .fh-links p {
            margin-bottom: 7px;
            color: var(--fh-texto-suave);
        }

        .fh-links p:last-child {
            margin-bottom: 0;
        }

        .fh-links a {
            color: var(--fh-vermelho);
            font-weight: 700;
            text-decoration: none;
        }

        .fh-links a:hover {
            color: var(--fh-vermelho-escuro);
            text-decoration: underline;
        }

        [data-bs-theme="dark"] .fh-links a {
            color: #e7b65c;
        }

        .alert {
            border-radius: 9px;
            font-size: 14px;
        }

        .fh-rodape {
            margin-top: 15px;
            color: var(--fh-texto-suave);
            text-align: center;
            font-size: 12px;
        }

        @keyframes fhEntrada {
            from {
                opacity: 0;
                transform: translateY(14px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 520px) {
            .fh-tema {
                top: 12px;
                right: 12px;
            }

            .fh-pagina {
                padding: 65px 14px 20px;
            }

            .fh-conteudo {
                padding: 23px 22px 20px;
            }

            .fh-logo {
                max-height: 85px;
            }

            .fh-acoes {
                flex-direction: column;
            }

            .btn-limpar {
                width: 100%;
            }
        }

        @media (max-height: 650px) and (min-width: 521px) {
            .fh-pagina {
                padding-top: 48px;
                padding-bottom: 12px;
            }

            .fh-conteudo {
                padding-top: 18px;
                padding-bottom: 16px;
            }

            .fh-logo {
                max-height: 72px;
            }

            .fh-logo-area {
                margin-bottom: 10px;
            }

            .fh-cabecalho {
                margin-bottom: 13px;
            }

            .fh-campo {
                margin-bottom: 10px;
            }

            .fh-links {
                margin-top: 13px;
                padding-top: 11px;
            }

            .fh-rodape {
                margin-top: 10px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>

    <button type="button" id="alternarTema" class="fh-tema" aria-label="Alternar entre modo claro e escuro">
        <span aria-hidden="true">◐</span>
        <span id="textoTema">Modo escuro</span>
    </button>

    <main class="fh-pagina">
        <section class="fh-cartao" aria-labelledby="tituloLogin">
            <div class="fh-faixa"></div>

            <div class="fh-conteudo">

                <div class="fh-logo-area">
                    <img src="assets/img/logo-foodhub.png" alt="FOOD HUB" class="fh-logo">
                    <span class="fh-etiqueta">Gestão inteligente</span>
                </div>

                <header class="fh-cabecalho">
                    <h1 class="fh-titulo" id="tituloLogin">Bem-vindo!</h1>
                    <p>Entre com seus dados para acessar o FoodHub.</p>
                </header>

                <?php if ($erro === 'credenciais'): ?>
                    <div class="alert alert-danger" role="alert">
                        Login ou senha inválidos.
                    </div>
                <?php elseif ($erro === '2fa_bloqueado'): ?>
                    <div class="alert alert-danger" role="alert">
                        3 tentativas sem sucesso! Favor realizar login novamente.
                    </div>
                <?php endif; ?>

                <form action="login_process.php" method="post">

                    <div class="fh-campo">
                        <label for="login">Login</label>
                        <input
                            type="text"
                            class="form-control"
                            id="login"
                            name="login"
                            maxlength="6"
                            autocomplete="username"
                            placeholder="Digite seu login"
                            required
                            autofocus
                        >
                    </div>

                    <div class="fh-campo">
                        <label for="senha">Senha</label>
                        <input
                            type="password"
                            class="form-control"
                            id="senha"
                            name="senha"
                            autocomplete="current-password"
                            placeholder="Digite sua senha"
                            required
                        >
                    </div>

                    <div class="fh-acoes">
                        <button type="submit" class="btn btn-fh flex-grow-1">
                            ENTRAR NO SISTEMA
                        </button>

                        <button type="reset" class="btn btn-limpar">
                            LIMPAR
                        </button>
                    </div>

                </form>

                <div class="fh-links">
                    <p>
                        <a href="recuperar_senha.php">Esqueci minha senha</a>
                    </p>

                    <p>
                        Novo por aqui?
                        <a href="cadastro.php">Cadastre-se</a>
                    </p>
                </div>

                <div class="fh-rodape">
                    FOOD HUB · Sistema de gestão
                </div>

            </div>
        </section>
    </main>

    <script>
        // Claro é o padrão; a escolha do usuário fica salva no navegador.
        (function () {
            var html = document.documentElement;
            var botao = document.getElementById('alternarTema');
            var texto = document.getElementById('textoTema');

            function aplicar(tema) {
                html.setAttribute('data-bs-theme', tema);

                texto.textContent = tema === 'dark'
                    ? 'Modo claro'
                    : 'Modo escuro';
            }

            var salvo = null;

            try {
                salvo = localStorage.getItem('foodhub-tema');
            } catch (e) {}

            aplicar(salvo === 'dark' ? 'dark' : 'light');

            botao.addEventListener('click', function () {
                var novo = html.getAttribute('data-bs-theme') === 'dark'
                    ? 'light'
                    : 'dark';

                aplicar(novo);

                try {
                    localStorage.setItem('foodhub-tema', novo);
                } catch (e) {}
            });
        })();
    </script>

</body>
</html>