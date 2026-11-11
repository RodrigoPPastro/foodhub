<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Controllers/UsuarioController.php';

AuthGuard::requireComum();

$resultado = null;
$sucesso   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = UsuarioController::alterarSenha(
        (int) SessionService::usuarioIdAtual(),
        $_POST['senha_atual']      ?? '',
        $_POST['nova_senha']       ?? '',
        $_POST['confirmar_senha']  ?? ''
    );
    $sucesso = $resultado['sucesso'];
}

$layoutTitulo = 'Alterar Senha';
$layoutAtivo  = 'senha';
require __DIR__ . '/_layout.php';
?>

<div class="page-heading">
    <div>
        <h1>Alterar Senha</h1>
        <p>Atualize a sua senha de acesso ao sistema.</p>
    </div>
</div>

<div class="fh-card-inner" style="max-width:480px;">
    <h2>Nova senha</h2>

    <?php if ($resultado !== null): ?>
        <div style="
            padding: .75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: .93rem;
            <?= $sucesso
                ? 'background:#dcfce7;color:#166534;border-left:4px solid #22c55e;'
                : 'background:#fee2e2;color:#991b1b;border-left:4px solid #ef4444;' ?>
        ">
            <?= htmlspecialchars($resultado['mensagem'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="post" action="alterar_senha.php" id="formSenha" novalidate>
        <div class="fh-form-group">
            <label for="senha_atual">Senha atual</label>
            <input type="password" id="senha_atual" name="senha_atual"
                   class="fh-input" required autocomplete="current-password">
        </div>

        <div class="fh-form-group">
            <label for="nova_senha">Nova senha <small style="font-weight:400;color:#888;">(exatamente 8 letras)</small></label>
            <input type="password" id="nova_senha" name="nova_senha"
                   class="fh-input" maxlength="8" required
                   autocomplete="new-password" pattern="[a-zA-Z]{8}"
                   title="A senha deve ter exatamente 8 letras (sem números ou símbolos)">
            <div class="fh-error-msg" id="errNova" style="display:none;"></div>
        </div>

        <div class="fh-form-group">
            <label for="confirmar_senha">Confirmar nova senha</label>
            <input type="password" id="confirmar_senha" name="confirmar_senha"
                   class="fh-input" maxlength="8" required
                   autocomplete="new-password">
            <div class="fh-error-msg" id="errConfirma" style="display:none;"></div>
        </div>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.5rem;">
            <button type="submit" class="btn-fh">Salvar nova senha</button>
            <button type="reset" class="btn-fh-outline">Limpar</button>
        </div>
    </form>
</div>

<script>
(function () {
    var form     = document.getElementById('formSenha');
    var nova     = document.getElementById('nova_senha');
    var confirma = document.getElementById('confirmar_senha');
    var errNova     = document.getElementById('errNova');
    var errConfirma = document.getElementById('errConfirma');

    function validarNova() {
        var v = nova.value;
        if (v.length > 0 && (v.length !== 8 || !/^[a-zA-Z]+$/.test(v))) {
            nova.classList.add('is-invalid');
            errNova.textContent = 'A senha deve ter exatamente 8 letras (sem números ou símbolos).';
            errNova.style.display = 'block';
            return false;
        }
        nova.classList.remove('is-invalid');
        errNova.style.display = 'none';
        return true;
    }
    function validarConfirma() {
        if (confirma.value.length > 0 && confirma.value !== nova.value) {
            confirma.classList.add('is-invalid');
            errConfirma.textContent = 'As senhas não conferem.';
            errConfirma.style.display = 'block';
            return false;
        }
        confirma.classList.remove('is-invalid');
        errConfirma.style.display = 'none';
        return true;
    }
    nova.addEventListener('input', function () { validarNova(); validarConfirma(); });
    confirma.addEventListener('input', validarConfirma);
    form.addEventListener('submit', function (e) {
        var ok = validarNova() & validarConfirma();
        if (!ok) e.preventDefault();
    });
})();
</script>

<?php require __DIR__ . '/_layout_fim.php'; ?>
