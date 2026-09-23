<?php
require_once __DIR__ . '/../src/Services/SessionService.php';
SessionService::start();

// Se já está logado, não faz sentido ver a tela de cadastro.
if (SessionService::estaAutenticado()) {
    header('Location: /public/dashboard.php');
    exit;
}

// TODO (back-end RF02): este formulário ainda envia para
// cadastro_process.php, que precisa validar server-side (nunca confiar
// só na validação do navegador), gravar em USUARIO + AUTENTICACAO_2FA
// com PasswordService::hash() e redirecionar para login.php em caso de
// sucesso. Combinar com o Rodrigo quem fica com essa parte (ver RF02
// nas notas do grupo — ainda não tem dono definido).
$erro = $_GET['erro'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FOOD HUB — Cadastro de Usuário</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/variables.css?v=2">
    <link rel="stylesheet" href="assets/css/custom.css?v=2">
</head>
<body>
    <main class="fh-page fh-page--wide">
        <button type="button" id="themeToggle" class="fh-theme-toggle" aria-label="Alternar tema claro/escuro">
            <svg class="fh-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>
            </svg>
            <svg class="fh-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            </svg>
        </button>

        <div class="fh-card fh-card--wide">
            <div class="fh-logo-badge">
                <img src="assets/img/logo.jpg" alt="FOOD HUB">
            </div>

            <h1>Criar conta</h1>
            <p class="fh-subtitle">Preencha seus dados para acessar o FOOD HUB.</p>

            <?php if ($erro === 'cpf_invalido'): ?>
                <div class="alert alert-danger py-2" role="alert">CPF inválido.</div>
            <?php elseif ($erro === 'login_em_uso'): ?>
                <div class="alert alert-danger py-2" role="alert">Esse login já está em uso. Escolha outro.</div>
            <?php elseif ($erro === 'cpf_em_uso'): ?>
                <div class="alert alert-danger py-2" role="alert">Já existe um cadastro com esse CPF.</div>
            <?php endif; ?>

            <form id="cadastroForm" action="cadastro_process.php" method="post" novalidate>

                <p class="fh-section-title">Dados pessoais</p>
                <div class="mb-3">
                    <label for="nome" class="fh-form-label">Nome completo</label>
                    <input
                        type="text" class="form-control" id="nome" name="nome"
                        minlength="15" maxlength="80" required
                    >
                    <div class="invalid-feedback">Informe seu nome completo (15 a 80 caracteres).</div>
                </div>

                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <label for="cpf" class="fh-form-label">CPF</label>
                        <input
                            type="text" class="form-control" id="cpf" name="cpf"
                            inputmode="numeric" placeholder="000.000.000-00"
                            maxlength="14" required
                        >
                        <div class="invalid-feedback">Informe um CPF válido.</div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <label for="telefone" class="fh-form-label">Telefone</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">+55</span>
                            <input
                                type="text" class="form-control" id="telefone"
                                inputmode="numeric" placeholder="XX-XXXXXXXXX"
                                maxlength="12" required
                            >
                            <div class="invalid-feedback">Informe um telefone válido.</div>
                        </div>
                        <input type="hidden" id="telefoneCompleto" name="telefone">
                    </div>
                </div>

                <p class="fh-section-title">Endereço</p>
                <div class="row">
                    <div class="col-sm-4 mb-1">
                        <label for="cep" class="fh-form-label">CEP</label>
                        <input
                            type="text" class="form-control" id="cep" name="cep"
                            inputmode="numeric" placeholder="00000000" maxlength="8" required
                        >
                    </div>
                    <div class="col-sm-8 mb-1">
                        <label for="logradouro" class="fh-form-label">Logradouro</label>
                        <input type="text" class="form-control" id="logradouro" name="logradouro" required>
                    </div>
                </div>
                <p id="cepStatus" class="fh-cep-status" aria-live="polite"></p>

                <div class="row">
                    <div class="col-sm-4 mb-3">
                        <label for="numero" class="fh-form-label">Número</label>
                        <input type="text" class="form-control" id="numero" name="numero" required>
                    </div>
                    <div class="col-sm-8 mb-3">
                        <label for="complemento" class="fh-form-label">Complemento (opcional)</label>
                        <input type="text" class="form-control" id="complemento" name="complemento">
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-5 mb-3">
                        <label for="bairro" class="fh-form-label">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" required>
                    </div>
                    <div class="col-sm-5 mb-3">
                        <label for="cidade" class="fh-form-label">Cidade</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" required>
                    </div>
                    <div class="col-sm-2 mb-3">
                        <label for="uf" class="fh-form-label">UF</label>
                        <input type="text" class="form-control" id="uf" name="uf" maxlength="2" required>
                    </div>
                </div>

                <p class="fh-section-title">Acesso</p>
                <div class="row">
                    <div class="col-sm-4 mb-3">
                        <label for="login" class="fh-form-label">Login</label>
                        <input
                            type="text" class="form-control" id="login" name="login"
                            maxlength="6" pattern="[A-Za-z]{6}" required
                        >
                        <div class="invalid-feedback">Exatamente 6 letras.</div>
                    </div>
                    <div class="col-sm-4 mb-3">
                        <label for="senha" class="fh-form-label">Senha</label>
                        <input
                            type="password" class="form-control" id="senha" name="senha"
                            minlength="8" required
                        >
                        <div class="invalid-feedback">Mínimo de 8 caracteres.</div>
                    </div>
                    <div class="col-sm-4 mb-3">
                        <label for="confirmarSenha" class="fh-form-label">Confirmar senha</label>
                        <input type="password" class="form-control" id="confirmarSenha" name="confirmarSenha" required>
                        <div class="invalid-feedback">As senhas precisam ser iguais.</div>
                    </div>
                </div>

                <p class="fh-section-title">Perguntas de segurança</p>
                <p class="fh-hint mb-3">Escolha 3 perguntas diferentes.</p>

                <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="row">
                    <div class="col-sm-7 mb-3">
                        <label for="pergunta<?= $i ?>" class="fh-form-label">Pergunta <?= $i ?></label>
                        <select class="form-select" id="pergunta<?= $i ?>" name="pergunta<?= $i ?>" required>
                            <option value="" selected disabled>Selecione...</option>
                            <option value="pet">Qual o nome do seu primeiro animal de estimação?</option>
                            <option value="mae">Qual o nome da sua mãe?</option>
                            <option value="escola">Qual foi o nome da sua primeira escola?</option>
                            <option value="comida">Qual sua comida favorita?</option>
                            <option value="rua">Qual o nome da rua onde você cresceu?</option>
                            <option value="filme">Qual seu filme favorito?</option>
                        </select>
                    </div>
                    <div class="col-sm-5 mb-3">
                        <label for="resposta<?= $i ?>" class="fh-form-label">Resposta</label>
                        <input type="text" class="form-control" id="resposta<?= $i ?>" name="resposta<?= $i ?>" required>
                    </div>
                </div>
                <?php endfor; ?>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary w-100">CRIAR CONTA</button>
                    <button type="reset" class="btn btn-outline-secondary">LIMPAR</button>
                </div>

                <div class="fh-links">
                    <a href="login.php">Já tenho conta — entrar</a>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js?v=5"></script>
</body>
</html>
