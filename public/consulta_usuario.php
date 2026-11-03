<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Controllers/UsuarioController.php';

AuthGuard::requireMaster();

$termo = trim($_GET['busca'] ?? '');
$usuarios = $termo !== '' ? UsuarioController::consultar($termo) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>FOOD HUB — Consulta de Usuários</title>
</head>
<body>
    <h1>Consulta de Usuários</h1>

    <?php if ($erroPdf = SessionService::getFlash('erro_pdf')): ?>
        <p role="alert"><strong><?= htmlspecialchars($erroPdf, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php endif; ?>

    <!-- RF26 (Desafio Plus): exportar lista completa de usuários em PDF -->
    <p><a href="usuarios_pdf.php" download>Exportar lista de usuários (PDF)</a></p>

    <form method="get">
        <input type="text" name="busca" placeholder="Pesquisar por nome"
               value="<?= htmlspecialchars($termo, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Buscar</button>
    </form>

    <table border="1" cellpadding="6">
        <tr><th>Nome</th><th>Login</th><th>E-mail</th><th>Cadastro</th><th>Ação</th></tr>
        <?php foreach ($usuarios as $u): ?>
        <tr>
            <td><?= htmlspecialchars($u['nome_completo'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($u['login'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($u['data_cadastro'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <form method="post" action="excluir_usuario.php"
                      onsubmit="return confirm('Confirma excluir este usuário?');">
                    <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                    <button type="submit">Excluir</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
