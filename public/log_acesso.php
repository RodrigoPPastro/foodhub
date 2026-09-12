<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Models/LogAcesso.php';

AuthGuard::requireMaster();

$nome = trim($_GET['nome'] ?? '');
$cpf  = trim($_GET['cpf'] ?? '');

$registros = LogAcesso::listar($nome !== '' ? $nome : null, $cpf !== '' ? $cpf : null);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>FOOD HUB — Log de Autenticação</title>
</head>
<body>
    <h1>Log de Autenticação</h1>

    <form method="get">
        <input type="text" name="nome" placeholder="Nome do usuário" value="<?= htmlspecialchars($nome) ?>">
        <input type="text" name="cpf" placeholder="CPF" value="<?= htmlspecialchars($cpf) ?>">
        <button type="submit">Filtrar</button>
        <a href="log_acesso.php">Todos</a>
    </form>

    <table border="1" cellpadding="6">
        <tr><th>Data/Hora</th><th>Usuário</th><th>2FA usado</th><th>Resultado</th></tr>
        <?php foreach ($registros as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['data_hora']) ?></td>
            <td><?= htmlspecialchars($r['nome_completo']) ?></td>
            <td><?= htmlspecialchars($r['tipo_2fa_utilizado']) ?></td>
            <td><?= htmlspecialchars($r['resultado']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
