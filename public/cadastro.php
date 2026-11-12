<?php
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Models/Autenticacao2FA.php';

SessionService::start();
// Master logado + ?funcionario=1: o mesmo formulário cadastra um FUNCIONÁRIO (depois vêm as permissões)
$modoFuncionario = SessionService::ehMaster() && isset($_GET['funcionario']);
$erros = SessionService::getFlash('cadastro_erros') ?? [];
$valores = SessionService::getFlash('cadastro_valores') ?? [];
$v = fn(string $campo) => htmlspecialchars($valores[$campo] ?? '');
$tel = fn(string $campo) => htmlspecialchars(preg_replace('/^\(\+55\)/', '', $valores[$campo] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#b3161b">
    <title>FoodHub — Criar conta</title>

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
            --fh-suave: #746b5e;
            --fh-borda: #e3d6b8;
            --fh-topo: #211f25;
            --fh-sombra: 0 15px 40px rgba(45, 30, 20, .12);
        }

        [data-bs-theme="dark"] {
            --fh-fundo: #171515;
            --fh-cartao: #211c1c;
            --fh-texto: #f3ead7;
            --fh-suave: #c0b5a3;
            --fh-borda: #453838;
            --fh-sombra: 0 15px 40px rgba(0, 0, 0, .35);
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Source Sans 3', sans-serif;
            color: var(--fh-texto);
            background:
                radial-gradient(circle at 5% 5%, rgba(201,154,46,.12), transparent 24%),
                radial-gradient(circle at 95% 90%, rgba(179,22,27,.08), transparent 26%),
                var(--fh-fundo);
        }

        h1, h2, legend, .fh-marca {
            font-family: 'Oswald', sans-serif;
        }

        .fh-tema {
            position: fixed;
            top: 14px;
            right: 16px;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 12px;
            border: 1px solid var(--fh-borda);
            border-radius: 30px;
            background: var(--fh-cartao);
            color: var(--fh-texto);
            font-size: 13px;
            font-weight: 700;
        }

        .fh-tema:hover { border-color: var(--fh-dourado); }

        .fh-pagina {
            width: 100%;
            max-width: 940px;
            margin: auto;
            padding: 68px 18px 35px;
        }

        .fh-cartao {
            overflow: hidden;
            border: 1px solid var(--fh-borda);
            border-radius: 16px;
            background: var(--fh-cartao);
            box-shadow: var(--fh-sombra);
            animation: entrada .35s ease both;
        }

        /* CABEÇALHO HARMONIZADO COM A TELA DE LOGIN */
        .fh-topo {
            position: relative;
            display: flex;
            align-items: center;
            gap: 22px;
            min-height: 155px;
            padding: 25px 34px;
            color: #fffaf0;
            background:
                radial-gradient(circle at 90% 10%, rgba(201,154,46,.20), transparent 35%),
                linear-gradient(115deg, #8f1015, #b3161b);
            border-bottom: 4px solid #c99a2e;
        }

        .fh-logo {
            display: block;
            width: auto;
            max-width: 155px;
            max-height: 95px;
            object-fit: contain;
        }

        [data-bs-theme="dark"] .fh-logo {
            filter: drop-shadow(0 0 3px rgba(255,255,255,.7));
        }

        .fh-topo-texto { min-width: 0; }

        .fh-etiqueta {
            display: inline-block;
            margin-bottom: 6px;
            padding: 4px 11px;
            background: rgba(255,250,240,.12);
            border: 1px solid rgba(255,250,240,.4);
            border-radius: 20px;
            color: #fffaf0;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .fh-topo h1 {
            margin: 0 0 4px;
            font-size: 30px;
            font-weight: 600;
        }

        .fh-topo p {
            margin: 0;
            color: #fffaf0;
            opacity: .88;
            font-size: 14px;
        }

        .fh-voltar {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 17px;
            color: var(--fh-vermelho);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .fh-voltar:hover {
            color: var(--fh-vermelho-escuro);
            text-decoration: underline;
        }

        [data-bs-theme="dark"] .fh-voltar,
        [data-bs-theme="dark"] .fh-rodape a {
            color: #e7b65c;
        }

        .fh-conteudo { padding: 24px 30px 25px; }

        .fh-intro {
            margin: 0 0 19px;
            color: var(--fh-suave);
            font-size: 13px;
        }

        fieldset {
            min-width: 0;
            padding: 13px 16px 5px;
            margin: 0 0 15px;
            border: 1px solid var(--fh-borda);
            border-radius: 11px;
            background: rgba(201,154,46,.035);
        }

        legend {
            float: none;
            width: auto;
            padding: 0 8px;
            margin: 0;
            color: var(--fh-vermelho);
            font-size: 17px;
            font-weight: 600;
        }

        [data-bs-theme="dark"] legend { color: #e7b65c; }

        .fh-numero {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 23px;
            height: 23px;
            margin-right: 5px;
            border-radius: 7px;
            background: var(--fh-vermelho);
            color: #fff;
            font-family: 'Source Sans 3', sans-serif;
            font-size: 12px;
            font-weight: 700;
        }

        .form-label {
            display: block;
            margin-bottom: 4px;
            color: var(--fh-texto);
            font-size: 13px;
            font-weight: 700;
        }

        .form-control,
        .input-group-text {
            min-height: 38px;
            border-color: var(--fh-borda);
            border-radius: 7px;
            background-color: var(--fh-fundo);
            color: var(--fh-texto);
            font-size: 13px;
        }

        .form-control { padding: 7px 10px; }

        .form-control::placeholder {
            color: var(--fh-suave);
            opacity: .75;
        }

        .form-control:focus {
            border-color: var(--fh-dourado);
            background-color: var(--fh-cartao);
            color: var(--fh-texto);
            box-shadow: 0 0 0 3px rgba(201,154,46,.16);
        }

        .input-group-text {
            padding: 6px 11px;
            font-weight: 700;
        }

        .input-group > .form-control {
            border-radius: 0 7px 7px 0;
        }

        .input-group > .input-group-text {
            border-radius: 7px 0 0 7px;
        }

        .form-text {
            margin-top: 3px;
            color: var(--fh-suave);
            font-size: 11px;
        }

        .invalid-feedback { font-size: 11px; }

        .fh-ajuda {
            margin: 0 0 12px;
            color: var(--fh-suave);
            font-size: 12px;
        }

        .fh-acoes {
            display: flex;
            gap: 10px;
            margin-top: 19px;
        }

        .btn-fh {
            min-height: 42px;
            border: 1px solid var(--fh-vermelho);
            border-radius: 8px;
            background: var(--fh-vermelho);
            color: #fff;
            font-family: 'Oswald', sans-serif;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: .5px;
            transition: .2s ease;
        }

        .btn-fh:hover {
            border-color: var(--fh-vermelho-escuro);
            background: var(--fh-vermelho-escuro);
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-limpar {
            min-height: 42px;
            padding: 0 17px;
            border: 1px solid var(--fh-borda);
            border-radius: 8px;
            background: transparent;
            color: var(--fh-texto);
            font-family: 'Oswald', sans-serif;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-limpar:hover {
            border-color: var(--fh-dourado);
            background: rgba(201,154,46,.1);
            color: var(--fh-texto);
        }

        .btn-fh:focus-visible,
        .btn-limpar:focus-visible,
        .fh-tema:focus-visible,
        .fh-voltar:focus-visible {
            outline: 3px solid var(--fh-dourado);
            outline-offset: 3px;
        }

        .fh-rodape {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-top: 16px;
            margin-top: 20px;
            border-top: 1px solid var(--fh-borda);
            color: var(--fh-suave);
            font-size: 13px;
        }

        .fh-rodape a {
            color: var(--fh-vermelho);
            font-weight: 700;
            text-decoration: none;
        }

        .fh-rodape a:hover { text-decoration: underline; }

        .alert {
            border-radius: 8px;
            font-size: 13px;
        }

        @keyframes entrada {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 650px) {
            .fh-pagina { padding: 65px 10px 20px; }

            .fh-topo {
                min-height: 130px;
                gap: 14px;
                padding: 20px 17px;
            }

            .fh-logo { max-width: 105px; max-height: 72px; }
            .fh-topo h1 { font-size: 25px; }
            .fh-topo p { font-size: 12px; }
            .fh-conteudo { padding: 19px 14px; }
            fieldset { padding: 12px 11px 4px; }
            .fh-acoes { flex-direction: column; }
            .btn-limpar { width: 100%; }
            .fh-rodape { align-items: flex-start; flex-direction: column; }
        }

        @media (max-width: 380px) {
            .fh-topo { gap: 10px; padding: 17px 12px; }
            .fh-logo { max-width: 82px; }
            .fh-topo h1 { font-size: 22px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .01ms !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
<link rel="stylesheet" href="assets/css/acessibilidade.css">
<script src="assets/js/acessibilidade.js"></script>
</head>
<body>

<button type="button" id="alternarTema" class="fh-tema"
        aria-label="Alternar entre modo claro e escuro">
    <span aria-hidden="true">◐</span>
    <span id="textoTema">Modo escuro</span>
</button>

<main class="fh-pagina">
    <section class="fh-cartao" aria-labelledby="tituloCadastro">

        <header class="fh-topo">
            <img src="assets/img/logo-foodhub.png" alt="FoodHub" class="fh-logo">
            <div class="fh-topo-texto">
                <span class="fh-etiqueta">Gestão inteligente</span>
                <h1 id="tituloCadastro"><?= $modoFuncionario ? 'Novo funcionário' : 'Criar sua conta' ?></h1>
                <p><?= $modoFuncionario ? 'Dados do funcionário. Depois de salvar, você define as telas que ele pode acessar.' : 'Seus dados para começar no FoodHub.' ?></p>
            </div>
        </header>

        <div class="fh-conteudo">

            <?php if ($modoFuncionario): ?>
            <a href="consulta_usuario.php" class="fh-voltar">
                <span aria-hidden="true">←</span> Voltar para Usuários
            </a>
            <?php else: ?>
            <a href="login.php" class="fh-voltar">
                <span aria-hidden="true">←</span> Voltar ao Login
            </a>
            <?php endif; ?>

            <p class="fh-intro">
                Preencha os campos abaixo. Os campos obrigatórios estão identificados no formulário.
            </p>

            <?php if (!empty($erros)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($erros as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="formCadastro" action="cadastro_process.php" method="post" novalidate>
                <?php if ($modoFuncionario): ?><input type="hidden" name="funcionario" value="1"><?php endif; ?>

                <!-- 01 - DADOS PESSOAIS -->
                <fieldset>
                    <legend><span class="fh-numero">01</span>Dados pessoais</legend>

                    <div class="row g-2">
                        <div class="col-12">
                            <label for="nome_completo" class="form-label">Nome completo</label>
                            <input type="text" class="form-control" id="nome_completo" name="nome_completo"
                                   maxlength="80" value="<?= $v('nome_completo') ?>" autocomplete="name" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4">
                            <label for="data_nascimento" class="form-label">Data de nascimento</label>
                            <input type="date" class="form-control" id="data_nascimento" name="data_nascimento"
                                   max="<?= date('Y-m-d') ?>" value="<?= $v('data_nascimento') ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4">
                            <label for="sexo" class="form-label">Sexo</label>
                            <input type="text" class="form-control" id="sexo" name="sexo"
                                   value="<?= $v('sexo') ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4">
                            <label for="cpf" class="form-label">CPF</label>
                            <input type="text" class="form-control" id="cpf" name="cpf" inputmode="numeric"
                                   placeholder="000.000.000-00" value="<?= $v('cpf') ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-12">
                            <label for="nome_materno" class="form-label">Nome materno</label>
                            <input type="text" class="form-control" id="nome_materno" name="nome_materno"
                                   value="<?= $v('nome_materno') ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </fieldset>

                <!-- 02 - CONTATO E ENDEREÇO -->
                <fieldset>
                    <legend><span class="fh-numero">02</span>Contato e endereço</legend>

                    <div class="row g-2">
                        <div class="col-12">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= $v('email') ?>" autocomplete="email" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="celular_num" class="form-label">Telefone celular</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">+55</span>
                                <input type="text" class="form-control" id="celular_num" inputmode="numeric"
                                       placeholder="21-999999999" value="<?= $tel('telefone_celular') ?>" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <input type="hidden" id="telefone_celular" name="telefone_celular"
                                   value="<?= $v('telefone_celular') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="fixo_num" class="form-label">
                                Telefone fixo <span class="fw-normal">(opcional)</span>
                            </label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">+55</span>
                                <input type="text" class="form-control" id="fixo_num" inputmode="numeric"
                                       placeholder="21-33334444" value="<?= $tel('telefone_fixo') ?>">
                                <div class="invalid-feedback"></div>
                            </div>
                            <input type="hidden" id="telefone_fixo" name="telefone_fixo"
                                   value="<?= $v('telefone_fixo') ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="cep" class="form-label">CEP</label>
                            <input type="text" class="form-control" id="cep" name="cep" inputmode="numeric"
                                   placeholder="20000-000" value="<?= $v('cep') ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-8">
                            <label for="endereco_completo" class="form-label">Endereço completo</label>
                            <input type="text" class="form-control" id="endereco_completo"
                                   name="endereco_completo" value="<?= $v('endereco_completo') ?>"
                                   autocomplete="street-address" required>
                            <div class="form-text" id="cepStatus"></div>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </fieldset>

                <!-- 03 - DADOS DE ACESSO -->
                <fieldset>
                    <legend><span class="fh-numero">03</span>Acesso à sua conta</legend>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="login" class="form-label">Login</label>
                            <input type="text" class="form-control" id="login" name="login" maxlength="6"
                                   value="<?= $v('login') ?>" autocomplete="username" required>
                            <div class="form-text">Exatamente 6 letras.</div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" maxlength="8"
                                   autocomplete="new-password" required>
                            <div class="form-text">Exatamente 8 letras.</div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-4">
                            <label for="confirmar_senha" class="form-label">Confirmar senha</label>
                            <input type="password" class="form-control" id="confirmar_senha"
                                   name="confirmar_senha" maxlength="8" autocomplete="new-password" required>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </fieldset>

                <!-- 04 - SEGURANÇA -->
                <fieldset>
                    <legend><span class="fh-numero">04</span>Proteção da sua conta</legend>

                    <p class="fh-ajuda">
                        Responda às três perguntas. Elas serão utilizadas na verificação em duas etapas.
                    </p>

                    <div class="row g-2">
                        <?php foreach (Autenticacao2FA::PERGUNTAS as $i => $pergunta): ?>
                            <div class="col-md-4">
                                <label for="resposta_2fa_<?= $i ?>" class="form-label">
                                    <?= htmlspecialchars($pergunta) ?>
                                </label>
                                <input type="text" class="form-control" id="resposta_2fa_<?= $i ?>"
                                       name="resposta_2fa[<?= $i ?>]" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="fh-acoes">
                    <button type="submit" class="btn btn-fh flex-grow-1">
                        CRIAR MINHA CONTA →
                    </button>
                    <button type="reset" class="btn btn-limpar">
                        LIMPAR CAMPOS
                    </button>
                </div>
            </form>

            <footer class="fh-rodape">
                <?php if ($modoFuncionario): ?>
                <a href="consulta_usuario.php">← Voltar para Usuários</a>
                <?php else: ?>
                <span>Já possui uma conta? <a href="login.php">Entrar no FoodHub</a></span>
                <a href="login.php">← Voltar ao Login</a>
                <?php endif; ?>
            </footer>

        </div>
    </section>
</main>

<script>
(function () {
    var form = document.getElementById('formCadastro');
    var $ = function (id) { return document.getElementById(id); };
    var soDigitos = function (s) { return (s || '').replace(/\D/g, ''); };

    /* ---------- Máscaras ---------- */
    function fmtCpf(d) {
        var s = d.slice(0, 3);
        if (d.length > 3) s += '.' + d.slice(3, 6);
        if (d.length > 6) s += '.' + d.slice(6, 9);
        if (d.length > 9) s += '-' + d.slice(9, 11);
        return s;
    }

    function fmtTel(d) {
        return d.length > 2 ? d.slice(0, 2) + '-' + d.slice(2) : d;
    }

    function fmtCep(d) {
        return d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
    }

    function aplicarMascara(input, formatar, maxDigitos) {
        var pos = input.selectionStart;
        var antes = soDigitos(input.value.slice(0, pos)).length;

        input.value = formatar(soDigitos(input.value).slice(0, maxDigitos));

        if (document.activeElement !== input) return;

        var i = 0, n = 0;

        if (antes > 0) {
            for (i = 0; i < input.value.length; i++) {
                if (/\d/.test(input.value[i])) n++;
                if (n === antes) { i++; break; }
            }
        }

        input.setSelectionRange(i, i);
    }

    var mascaras = [
        { el: $('cpf'), fmt: fmtCpf, max: 11 },
        { el: $('celular_num'), fmt: fmtTel, max: 11 },
        { el: $('fixo_num'), fmt: fmtTel, max: 10 },
        { el: $('cep'), fmt: fmtCep, max: 8 }
    ];

    mascaras.forEach(function (m) {
        m.el.addEventListener('input', function () {
            aplicarMascara(m.el, m.fmt, m.max);
            sincronizarTelefones();
        });

        m.el.addEventListener('keydown', function (e) {
            var p = m.el.selectionStart;

            if (e.key === 'Backspace' && p === m.el.selectionEnd &&
                p > 0 && !/\d/.test(m.el.value[p - 1])) {
                m.el.setSelectionRange(p - 1, p - 1);
            }
        });

        m.el.value = m.fmt(soDigitos(m.el.value).slice(0, m.max));
    });

    /* ---------- Telefones ---------- */
    function sincronizarTelefones() {
        $('telefone_celular').value = $('celular_num').value
            ? '(+55)' + $('celular_num').value
            : '';

        $('telefone_fixo').value = $('fixo_num').value
            ? '(+55)' + $('fixo_num').value
            : '';
    }

    sincronizarTelefones();

    /* ---------- Validações ---------- */
    function cpfValido(c) {
        c = soDigitos(c);

        if (c.length !== 11 || /^(\d)\1+$/.test(c)) return false;

        for (var t = 9; t < 11; t++) {
            var soma = 0;

            for (var i = 0; i < t; i++) {
                soma += parseInt(c[i], 10) * (t + 1 - i);
            }

            if (((soma * 10) % 11) % 10 !== parseInt(c[t], 10)) return false;
        }

        return true;
    }

    function mensagem(el) {
        var val = el.value.trim();

        if (el.required && val === '') return 'Preencha este campo.';

        switch (el.id) {
            case 'nome_completo':
                if (val.length < 15) return 'Digite o nome completo (mínimo de 15 caracteres).';
                break;

            case 'cpf':
                if (!cpfValido(val)) return 'CPF inválido. Confira os números.';
                break;

            case 'email':
                el.setCustomValidity('');
                if (val && el.validity.typeMismatch) return 'Digite um e-mail válido.';
                break;

            case 'celular_num':
                if (soDigitos(val).length !== 11)
                    return 'Use o DDD + 9 dígitos. Exemplo: 21-999999999';
                break;

            case 'fixo_num':
                if (val && soDigitos(val).length !== 10)
                    return 'Use o DDD + 8 dígitos. Exemplo: 21-33334444';
                break;

            case 'cep':
                if (soDigitos(val).length !== 8) return 'O CEP tem 8 dígitos.';
                break;

            case 'login':
                if (!/^\p{L}{6}$/u.test(val))
                    return 'O login deve ter exatamente 6 letras.';
                break;

            case 'senha':
                if (!/^\p{L}{8}$/u.test(el.value))
                    return 'A senha deve ter exatamente 8 letras.';
                break;

            case 'confirmar_senha':
                if (el.value !== $('senha').value) return 'As senhas não conferem.';
                break;
        }

        return '';
    }

    function validar(el) {
        var msg = mensagem(el);
        el.setCustomValidity(msg);

        var fb = el.parentElement.querySelector('.invalid-feedback');
        if (fb) fb.textContent = msg;

        if (el.dataset.tocado) {
            el.classList.toggle('is-invalid', !!msg);
            el.classList.toggle('is-valid', !msg && el.value !== '');
        }

        return msg === '';
    }

    var campos = Array.prototype.slice.call(
        form.querySelectorAll('input:not([type=hidden])')
    );

    campos.forEach(function (el) {
        el.addEventListener('blur', function () {
            el.dataset.tocado = '1';
            validar(el);
        });

        el.addEventListener('input', function () {
            if (el.id === 'confirmar_senha') el.dataset.tocado = '1';

            if (el.dataset.tocado) validar(el);

            if (el.id === 'senha' && $('confirmar_senha').dataset.tocado) {
                validar($('confirmar_senha'));
            }
        });
    });

    form.addEventListener('submit', function (e) {
        sincronizarTelefones();

        var primeiroInvalido = null;

        campos.forEach(function (el) {
            el.dataset.tocado = '1';

            if (!validar(el) && !primeiroInvalido) {
                primeiroInvalido = el;
            }
        });

        if (primeiroInvalido) {
            e.preventDefault();
            primeiroInvalido.focus();
        }
    });

    /* ---------- Limpar formulário ---------- */
    form.addEventListener('reset', function (e) {
        e.preventDefault();

        campos.forEach(function (el) {
            el.value = '';
            el.setCustomValidity('');
            el.classList.remove('is-invalid', 'is-valid');
            delete el.dataset.tocado;
        });

        sincronizarTelefones();
        $('cepStatus').textContent = '';
    });

    /* ---------- Consulta de CEP ---------- */
    $('cep').addEventListener('blur', function () {
        var cep = soDigitos(this.value);

        if (cep.length !== 8) return;

        var status = $('cepStatus');
        status.textContent = 'Buscando endereço...';

        fetch('api/cep.php?cep=' + cep)
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (dados.erro) {
                    status.textContent = 'CEP não encontrado. Preencha o endereço manualmente.';
                    return;
                }

                $('endereco_completo').value =
                    dados.logradouro + ', ' + dados.bairro + ', ' +
                    dados.cidade + '-' + dados.uf;

                status.textContent = '';

                if ($('endereco_completo').dataset.tocado) {
                    validar($('endereco_completo'));
                }
            })
            .catch(function () {
                status.textContent =
                    'Não foi possível buscar o CEP. Preencha o endereço manualmente.';
            });
    });
})();
</script>

</body>
</html>