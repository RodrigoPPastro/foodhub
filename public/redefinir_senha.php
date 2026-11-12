<?php

require_once __DIR__ . '/../src/Services/SessionService.php';

SessionService::start();

// O usuário só pode chegar aqui depois de validar
// corretamente a pergunta de segurança.
$autorizacao = SessionService::recuperacaoAutorizada();

if ($autorizacao === null) {
    SessionService::setFlash(
        'recuperacao_erro',
        'Sua autorização para redefinir a senha expirou ou não é válida.'
    );

    header('Location: /foodhub/public/recuperar_senha.php');
    exit;
}

$erro = SessionService::getFlash('recuperacao_erro');

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Redefinir Senha - FOOD HUB</title>
<link rel="stylesheet" href="assets/css/acessibilidade.css">
<script src="assets/js/acessibilidade.js"></script>
</head>

<body>

    <main>

        <h1>Redefinir Senha</h1>

        <p>
            Cadastre uma nova senha para sua conta.
        </p>

        <?php if ($erro !== null): ?>
            <p>
                <?= htmlspecialchars(
                    (string) $erro,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>
        <?php endif; ?>

        <form
            action="/foodhub/public/redefinir_senha_process.php"
            method="POST"
        >

            <div>
                <label for="nova_senha">
                    Nova senha:
                </label>

                <input
                    type="password"
                    id="nova_senha"
                    name="nova_senha"
                    required
                    autocomplete="new-password"
                >
            </div>

            <div>
                <label for="confirmar_senha">
                    Confirmar nova senha:
                </label>

                <input
                    type="password"
                    id="confirmar_senha"
                    name="confirmar_senha"
                    required
                    autocomplete="new-password"
                >
            </div>

            <button type="submit">
                Redefinir senha
            </button>

        </form>

        <p>
            Após redefinir sua senha, você deverá fazer login novamente
            e realizar a validação do 2FA.
        </p>

    </main>

</body>

</html>