<?php
$tipo = $_GET['tipo'] ?? 'generico';

$mensagens = [
    'conexao'        => 'Não foi possível conectar ao sistema no momento. Tente novamente em instantes.',
    'acesso_negado'  => 'Você não tem permissão para acessar esta página.',
    '2fa_ausente'    => 'Não foi possível validar sua autenticação. Contate o suporte.',
    'generico'       => 'Ocorreu um erro inesperado.',
];

$mensagem = $mensagens[$tipo] ?? $mensagens['generico'];
?>
<!-- NOTA PRA DANDARA: estilizar mantendo a identidade visual (modal/toast, não alert()). -->
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>FOOD HUB — Erro</title>
</head>
<body>
    <h1>Ops!</h1>
    <p><?= htmlspecialchars($mensagem) ?></p>
    <a href="/public/login.php">Voltar ao login</a>
</body>
</html>
