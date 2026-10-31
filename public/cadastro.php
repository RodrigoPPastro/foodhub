<?php
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Models/Autenticacao2FA.php';

SessionService::start();
$erros = SessionService::getFlash('cadastro_erros') ?? [];
$valores = SessionService::getFlash('cadastro_valores') ?? [];
$v = fn(string $campo) => htmlspecialchars($valores[$campo] ?? '');
// Os telefones voltam da sessão como "(+55)21-999999999"; no campo visível o "+55" é fixo, então tiramos o prefixo.
$tel = fn(string $campo) => htmlspecialchars(preg_replace('/^\(\+55\)/', '', $valores[$campo] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FOOD HUB — Cadastro</title>

    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --fh-vermelho: #b3161b;
            --fh-vermelho-escuro: #8f1015;
            --fh-dourado: #c99a2e;
            --fh-fundo: #f6efe0;
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
        }
        h1, legend, .fh-titulo { font-family: 'Oswald', sans-serif; font-weight: 600; }

        .fh-cartao {
            width: 100%;
            max-width: 760px;
            background: var(--fh-cartao);
            border: 1px solid var(--fh-borda);
            border-top: 4px solid var(--fh-vermelho);
            border-radius: .5rem;
        }
        .fh-logo { max-height: 110px; width: auto; max-width: 100%; }
        [data-bs-theme="dark"] .fh-logo { filter: drop-shadow(0 0 3px rgba(243, 234, 215, .85)); }

        .form-control, .input-group-text {
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
        .form-control.is-invalid:focus { border-color: var(--bs-form-invalid-border-color); }

        fieldset { border: 1px solid var(--fh-borda); border-radius: .5rem; padding: 1rem 1rem .25rem; }
        legend { float: none; width: auto; font-size: 1.1rem; padding: 0 .5rem; margin-bottom: 0; }

        .btn-fh {
            background: var(--fh-vermelho);
            border-color: var(--fh-vermelho);
            color: #fff;
            font-family: 'Oswald', sans-serif;
            letter-spacing: .04em;
        }
        .btn-fh:hover, .btn-fh:focus { background: var(--fh-vermelho-escuro); border-color: var(--fh-vermelho-escuro); color: #fff; }
        .btn-fh:focus-visible { box-shadow: 0 0 0 .2rem rgba(201, 154, 46, .5); }
        .btn-limpar { border-color: var(--fh-borda); color: var(--fh-texto); }
        .btn-limpar:hover { border-color: var(--fh-dourado); color: var(--fh-texto); }

        a { color: var(--fh-vermelho); }
        [data-bs-theme="dark"] a { color: var(--fh-dourado); }

        .fh-tema {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 10;
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

    <main class="d-flex justify-content-center px-3 py-5">
        <div class="fh-cartao shadow-sm p-4 p-md-5">

            <div class="text-center mb-3">
                <img src="assets/img/logo-foodhub.png" alt="FOOD HUB" class="fh-logo">
            </div>

            <h1 class="h4 text-center mb-4">Criar conta</h1>

            <?php if (!empty($erros)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="formCadastro" action="cadastro_process.php" method="post" novalidate>

                <div class="row g-3">

                    <div class="col-12">
                        <label for="nome_completo" class="form-label">Nome completo</label>
                        <input type="text" class="form-control" id="nome_completo" name="nome_completo"
                               maxlength="80" value="<?= $v('nome_completo') ?>" autocomplete="name" required>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-6">
                        <label for="data_nascimento" class="form-label">Data de nascimento</label>
                        <input type="date" class="form-control" id="data_nascimento" name="data_nascimento"
                               max="<?= date('Y-m-d') ?>" value="<?= $v('data_nascimento') ?>" required>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-6">
                        <label for="sexo" class="form-label">Sexo</label>
                        <input type="text" class="form-control" id="sexo" name="sexo"
                               value="<?= $v('sexo') ?>" required>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-12">
                        <label for="nome_materno" class="form-label">Nome materno</label>
                        <input type="text" class="form-control" id="nome_materno" name="nome_materno"
                               value="<?= $v('nome_materno') ?>" required>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-6">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" inputmode="numeric"
                               placeholder="000.000.000-00" value="<?= $v('cpf') ?>" required>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-6">
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
                        <!-- Este campo oculto é o que vai para o back-end, no formato (+55)XX-XXXXXXXXX -->
                        <input type="hidden" id="telefone_celular" name="telefone_celular" value="<?= $v('telefone_celular') ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="fixo_num" class="form-label">Telefone fixo <span class="text-body-secondary">(opcional)</span></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">+55</span>
                            <input type="text" class="form-control" id="fixo_num" inputmode="numeric"
                                   placeholder="21-33334444" value="<?= $tel('telefone_fixo') ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                        <input type="hidden" id="telefone_fixo" name="telefone_fixo" value="<?= $v('telefone_fixo') ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="cep" class="form-label">CEP</label>
                        <input type="text" class="form-control" id="cep" name="cep" inputmode="numeric"
                               placeholder="20000-000" value="<?= $v('cep') ?>" required>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-8">
                        <label for="endereco_completo" class="form-label">Endereço completo</label>
                        <input type="text" class="form-control" id="endereco_completo" name="endereco_completo"
                               value="<?= $v('endereco_completo') ?>" autocomplete="street-address" required>
                        <div class="form-text" id="cepStatus"></div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-4">
                        <label for="login" class="form-label">Login</label>
                        <input type="text" class="form-control" id="login" name="login" maxlength="6"
                               value="<?= $v('login') ?>" autocomplete="username" required>
                        <div class="form-text">6 letras.</div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-4">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" maxlength="8"
                               autocomplete="new-password" required>
                        <div class="form-text">8 letras.</div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-md-4">
                        <label for="confirmar_senha" class="form-label">Confirmação da senha</label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha"
                               maxlength="8" autocomplete="new-password" required>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="col-12">
                        <fieldset>
                            <legend>Perguntas de segurança</legend>
                            <p class="small text-body-secondary">Responda as 3. Elas serão usadas na verificação em duas etapas.</p>
                            <?php foreach (Autenticacao2FA::PERGUNTAS as $i => $pergunta): ?>
                                <div class="mb-3">
                                    <label for="resposta_2fa_<?= $i ?>" class="form-label"><?= htmlspecialchars($pergunta) ?></label>
                                    <input type="text" class="form-control" id="resposta_2fa_<?= $i ?>"
                                           name="resposta_2fa[<?= $i ?>]" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    </div>

                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-fh flex-grow-1">ENVIAR</button>
                    <button type="reset" class="btn btn-limpar">LIMPAR TELA</button>
                </div>
            </form>

            <p class="text-center mt-4 mb-0">Já tem conta? <a href="login.php">Entrar</a></p>
        </div>
    </main>

    <script>
    (function () {
        var form = document.getElementById('formCadastro');
        var $ = function (id) { return document.getElementById(id); };
        var soDigitos = function (s) { return (s || '').replace(/\D/g, ''); };

        /* ---------- Tema (claro é o padrão; escolha fica salva) ---------- */
        var html = document.documentElement, botaoTema = $('alternarTema');
        function aplicarTema(t) {
            html.setAttribute('data-bs-theme', t);
            botaoTema.textContent = t === 'dark' ? 'Modo claro' : 'Modo escuro';
        }
        var salvo = null;
        try { salvo = localStorage.getItem('foodhub-tema'); } catch (e) {}
        aplicarTema(salvo === 'dark' ? 'dark' : 'light');
        botaoTema.addEventListener('click', function () {
            var novo = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            aplicarTema(novo);
            try { localStorage.setItem('foodhub-tema', novo); } catch (e) {}
        });

        /* ---------- Máscaras (mantêm o cursor no lugar) ---------- */
        function fmtCpf(d) {
            var s = d.slice(0, 3);
            if (d.length > 3) s += '.' + d.slice(3, 6);
            if (d.length > 6) s += '.' + d.slice(6, 9);
            if (d.length > 9) s += '-' + d.slice(9, 11);
            return s;
        }
        function fmtTel(d) { return d.length > 2 ? d.slice(0, 2) + '-' + d.slice(2) : d; }
        function fmtCep(d) { return d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d; }

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
            { el: $('cpf'),         fmt: fmtCpf, max: 11 },
            { el: $('celular_num'), fmt: fmtTel, max: 11 },
            { el: $('fixo_num'),    fmt: fmtTel, max: 10 },
            { el: $('cep'),         fmt: fmtCep, max: 8 }
        ];
        mascaras.forEach(function (m) {
            m.el.addEventListener('input', function () { aplicarMascara(m.el, m.fmt, m.max); sincronizarTelefones(); });
            // Backspace logo depois de um separador apaga o dígito anterior, em vez de "travar".
            m.el.addEventListener('keydown', function (e) {
                var p = m.el.selectionStart;
                if (e.key === 'Backspace' && p === m.el.selectionEnd && p > 0 && !/\d/.test(m.el.value[p - 1])) {
                    m.el.setSelectionRange(p - 1, p - 1);
                }
            });
            m.el.value = m.fmt(soDigitos(m.el.value).slice(0, m.max));
        });

        /* ---------- Campos ocultos dos telefones: (+55)XX-XXXXXXXXX ---------- */
        function sincronizarTelefones() {
            $('telefone_celular').value = $('celular_num').value ? '(+55)' + $('celular_num').value : '';
            $('telefone_fixo').value    = $('fixo_num').value    ? '(+55)' + $('fixo_num').value    : '';
        }
        sincronizarTelefones();

        /* ---------- Validações (mesmas regras do back-end) ---------- */
        function cpfValido(c) {
            c = soDigitos(c);
            if (c.length !== 11 || /^(\d)\1+$/.test(c)) return false;
            for (var t = 9; t < 11; t++) {
                var soma = 0;
                for (var i = 0; i < t; i++) soma += parseInt(c[i], 10) * (t + 1 - i);
                if (((soma * 10) % 11) % 10 !== parseInt(c[t], 10)) return false;
            }
            return true;
        }

        function mensagem(el) {
            var val = el.value.trim();
            if (el.required && val === '') return 'Preencha este campo.';
            switch (el.id) {
                case 'nome_completo': if (val.length < 15) return 'Digite o nome completo (mínimo de 15 caracteres).'; break;
                case 'cpf':           if (!cpfValido(val)) return 'CPF inválido. Confira os números.'; break;
                case 'email':         el.setCustomValidity(''); if (val && el.validity.typeMismatch) return 'Digite um e-mail válido.'; break;
                case 'celular_num':   if (soDigitos(val).length !== 11) return 'Use o DDD + 9 dígitos. Exemplo: 21-999999999'; break;
                case 'fixo_num':      if (val && soDigitos(val).length !== 10) return 'Use o DDD + 8 dígitos. Exemplo: 21-33334444'; break;
                case 'cep':           if (soDigitos(val).length !== 8) return 'O CEP tem 8 dígitos.'; break;
                case 'login':         if (!/^\p{L}{6}$/u.test(val)) return 'O login deve ter exatamente 6 letras.'; break;
                case 'senha':         if (!/^\p{L}{8}$/u.test(el.value)) return 'A senha deve ter exatamente 8 letras.'; break;
                case 'confirmar_senha': if (el.value !== $('senha').value) return 'As senhas não conferem.'; break;
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

        var campos = Array.prototype.slice.call(form.querySelectorAll('input:not([type=hidden])'));
        campos.forEach(function (el) {
            el.addEventListener('blur', function () { el.dataset.tocado = '1'; validar(el); });
            el.addEventListener('input', function () {
                if (el.id === 'confirmar_senha') el.dataset.tocado = '1'; // confirmação valida enquanto digita
                if (el.dataset.tocado) validar(el);
                if (el.id === 'senha' && $('confirmar_senha').dataset.tocado) validar($('confirmar_senha'));
            });
        });

        form.addEventListener('submit', function (e) {
            sincronizarTelefones();
            var primeiroInvalido = null;
            campos.forEach(function (el) {
                el.dataset.tocado = '1';
                if (!validar(el) && !primeiroInvalido) primeiroInvalido = el;
            });
            if (primeiroInvalido) {
                e.preventDefault();
                primeiroInvalido.focus();
            }
        });

        /* ---------- LIMPAR TELA: esvazia tudo (inclusive o que voltou de um erro) ---------- */
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

        /* ---------- CEP: autocompleta o endereço via api/cep.php ---------- */
        $('cep').addEventListener('blur', function () {
            var cep = soDigitos(this.value);
            if (cep.length !== 8) return;
            var status = $('cepStatus');
            status.textContent = 'Buscando endereço...';
            fetch('api/cep.php?cep=' + cep)
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (dados.erro) { status.textContent = 'CEP não encontrado. Preencha o endereço manualmente.'; return; }
                    $('endereco_completo').value =
                        dados.logradouro + ', ' + dados.bairro + ', ' + dados.cidade + '-' + dados.uf;
                    status.textContent = '';
                    if ($('endereco_completo').dataset.tocado) validar($('endereco_completo'));
                })
                .catch(function () { status.textContent = 'Não foi possível buscar o CEP. Preencha o endereço manualmente.'; });
        });
    })();
    </script>
</body>
</html>
