<?php
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Models/Autenticacao2FA.php';

SessionService::start();
$erros = SessionService::getFlash('cadastro_erros') ?? [];
$valores = SessionService::getFlash('cadastro_valores') ?? [];
$v = fn(string $campo) => htmlspecialchars($valores[$campo] ?? '');
?>
<!-- NOTA PRA DANDARA: esqueleto funcional do RF02. Estilizar mantendo os
     "name" dos campos. O JS de autocompletar endereço deve chamar
     GET /public/api/cep.php?cep=XXXXXXXX -->
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>FOOD HUB — Cadastro</title>
</head>
<body>
    <h1>Criar conta</h1>

    <?php if (!empty($erros)): ?>
        <ul style="color:red">
            <?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form action="cadastro_process.php" method="post">
        <label>Nome completo</label>
        <input type="text" name="nome_completo" value="<?= $v('nome_completo') ?>" required>

        <label>Data de nascimento</label>
        <input type="date" name="data_nascimento" value="<?= $v('data_nascimento') ?>" required>

        <label>Sexo</label>
        <input type="text" name="sexo" value="<?= $v('sexo') ?>" required>

        <label>Nome materno</label>
        <input type="text" name="nome_materno" value="<?= $v('nome_materno') ?>" required>

        <label>CPF</label>
        <input type="text" name="cpf" value="<?= $v('cpf') ?>" placeholder="000.000.000-00" required>

        <label>E-mail</label>
        <input type="email" name="email" value="<?= $v('email') ?>" required>

        <label>Telefone celular</label>
        <input type="text" name="telefone_celular" value="<?= $v('telefone_celular') ?>" placeholder="(+55)21-999999999" required>

        <label>Telefone fixo</label>
        <input type="text" name="telefone_fixo" value="<?= $v('telefone_fixo') ?>" placeholder="(+55)21-33334444">

        <label>CEP</label>
        <input type="text" id="cep" name="cep" value="<?= $v('cep') ?>" placeholder="20000-000" required>

        <label>Endereço completo</label>
        <input type="text" id="endereco_completo" name="endereco_completo" value="<?= $v('endereco_completo') ?>" required>

        <label>Login</label>
        <input type="text" name="login" maxlength="6" value="<?= $v('login') ?>" required>

        <label>Senha</label>
        <input type="password" name="senha" maxlength="8" required>

        <label>Confirmação da senha</label>
        <input type="password" name="confirmar_senha" maxlength="8" required>

        <fieldset>
            <legend>Perguntas de segurança — responda as 3</legend>
            <?php foreach (Autenticacao2FA::PERGUNTAS as $i => $pergunta): ?>
                <label><?= htmlspecialchars($pergunta) ?></label>
                <input type="text" name="resposta_2fa[<?= $i ?>]" required>
            <?php endforeach; ?>
        </fieldset>

        <button type="submit">ENVIAR</button>
        <button type="reset">LIMPAR TELA</button>
    </form>

    <script>
    // Autocompletar endereço via CEP (fallback manual se a API falhar).
    document.getElementById('cep').addEventListener('blur', function () {
        var cep = this.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        fetch('api/cep.php?cep=' + cep)
            .then(function (r) { return r.json(); })
            .then(function (dados) {
                if (dados.erro) return; // usuário preenche manualmente
                document.getElementById('endereco_completo').value =
                    dados.logradouro + ', ' + dados.bairro + ', ' + dados.cidade + '-' + dados.uf;
            })
            .catch(function () { /* offline: mantém preenchimento manual */ });
    });
    </script>
</body>
</html>
