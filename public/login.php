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
    <title>FOOD HUB — Login</title>

    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --fh-vermelho: #b3161b;
            --fh-vermelho-escuro: #8f1015;
            --fh-dourado: #c99a2e;
            --fh-fundo: #f6efe0;      /* creme, não branco puro */
            --fh-cartao: #fffaf0;
            --fh-texto: #1a1a1a;
            --fh-borda: #e3d6b8;
        }
        [data-bs-theme="dark"] {
            --fh-fundo: #141212;
            --fh-cartao: #1f1b1b;
            --fh-texto: #f3ead7;
            --fh-borda: #3a3232;
        }

        body {
            font-family: 'Source Sans 3', system-ui, sans-serif;
            background: var(--fh-fundo);
            color: var(--fh-texto);
            min-height: 100vh;
        }
        h1, .fh-titulo { font-family: 'Oswald', sans-serif; font-weight: 600; }

        .fh-cartao {
            width: 100%;
            max-width: 420px;
            background: var(--fh-cartao);
            border: 1px solid var(--fh-borda);
            border-top: 4px solid var(--fh-vermelho);
            border-radius: .5rem;
        }
        .fh-logo { max-height: 150px; width: auto; max-width: 100%; }
        /* O contorno preto da logo some no fundo escuro; um brilho leve devolve o contraste. */
        [data-bs-theme="dark"] .fh-logo { filter: drop-shadow(0 0 3px rgba(243, 234, 215, .85)); }

        .form-control {
            background-color: var(--fh-fundo);
            border-color: var(--fh-borda);
            color: var(--fh-texto);
        }
        .form-control:focus {
            background-color: var(--fh-fundo);
            color: var(--fh-texto);
            border-color: var(--fh-dourado);
            box-shadow: 0 0 0 .2rem rgba(201, 154, 46, .35);
        }

        .btn-fh {
            background: var(--fh-vermelho);
            border-color: var(--fh-vermelho);
            color: #fff;
            font-family: 'Oswald', sans-serif;
            letter-spacing: .04em;
        }
        .btn-fh:hover, .btn-fh:focus {
            background: var(--fh-vermelho-escuro);
            border-color: var(--fh-vermelho-escuro);
            color: #fff;
        }
        .btn-fh:focus-visible { box-shadow: 0 0 0 .2rem rgba(201, 154, 46, .5); }
        .btn-limpar {
            border-color: var(--fh-borda);
            color: var(--fh-texto);
        }
        .btn-limpar:hover { border-color: var(--fh-dourado); color: var(--fh-texto); }

        a { color: var(--fh-vermelho); }
        [data-bs-theme="dark"] a { color: var(--fh-dourado); }

        .fh-tema {
            position: fixed;
            top: 1rem;
            right: 1rem;
            border-color: var(--fh-borda);
            color: var(--fh-texto);
            background: var(--fh-cartao);
        }
    </style>
</head>
<body>

    <button type="button" id="alternarTema" class="btn btn-sm fh-tema" aria-label="Alternar entre modo claro e escuro">
        Modo escuro
    </button>

    <main class="d-flex align-items-center justify-content-center px-3 py-5" style="min-height:100vh">
        <div class="fh-cartao shadow-sm p-4 p-md-5">

            <div class="text-center mb-4">
                <img src="assets/img/logo-foodhub.png" alt="FOOD HUB" class="fh-logo">
            </div>

            <h1 class="h4 text-center mb-4">Entrar</h1>

            <?php if ($erro === 'credenciais'): ?>
                <div class="alert alert-danger" role="alert">Login ou senha inválidos.</div>
            <?php elseif ($erro === '2fa_bloqueado'): ?>
                <div class="alert alert-danger" role="alert">3 tentativas sem sucesso! Favor realizar login novamente.</div>
            <?php endif; ?>

            <form action="login_process.php" method="post">
                <div class="mb-3">
                    <label for="login" class="form-label">Login</label>
                    <input type="text" class="form-control" id="login" name="login"
                           maxlength="6" autocomplete="username" required autofocus>
                </div>

                <div class="mb-4">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha"
                           autocomplete="current-password" required>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-fh flex-grow-1">ENTRAR</button>
                    <button type="reset" class="btn btn-limpar">LIMPAR</button>
                </div>
            </form>

            <div class="text-center mt-4">
                <p class="mb-2"><a href="recuperar_senha.php">Esqueci minha senha</a></p>
                <p class="mb-0">Novo por aqui? <a href="cadastro.php">Cadastre-se</a></p>
            </div>
        </div>
    </main>

    <script>
        // Claro (creme) é o padrão; a escolha do usuário fica salva no navegador.
        (function () {
            var html = document.documentElement;
            var botao = document.getElementById('alternarTema');

            function aplicar(tema) {
                html.setAttribute('data-bs-theme', tema);
                botao.textContent = tema === 'dark' ? 'Modo claro' : 'Modo escuro';
            }

            var salvo = null;
            try { salvo = localStorage.getItem('foodhub-tema'); } catch (e) {}
            aplicar(salvo === 'dark' ? 'dark' : 'light');

            botao.addEventListener('click', function () {
                var novo = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                aplicar(novo);
                try { localStorage.setItem('foodhub-tema', novo); } catch (e) {}
            });
        })();
    </script>
</body>
</html>
