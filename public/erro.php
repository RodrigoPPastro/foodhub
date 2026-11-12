<?php
$tipo = $_GET['tipo'] ?? 'generico';

$mensagens = [
    'conexao'        => 'Não foi possível conectar ao sistema no momento. Tente novamente em instantes.',
    'acesso_negado'  => 'Você não tem permissão para acessar esta página.',
    'sem_permissoes' => 'Seu usuário ainda não tem nenhuma tela liberada. Peça ao administrador para definir suas permissões.',
    '2fa_ausente'    => 'Não foi possível validar sua autenticação. Contate o suporte.',
    'generico'       => 'Ocorreu um erro inesperado.',
];

$mensagem = $mensagens[$tipo] ?? $mensagens['generico'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FOOD HUB — Erro</title>
<link rel="stylesheet" href="assets/css/acessibilidade.css">
<style>
    /* Página de erro simples, no tema claro/escuro escolhido pelo usuário */
    body { margin: 0; min-height: 100vh; display: grid; place-content: center; text-align: center; font-family: "Source Sans 3", Arial, sans-serif; background: #f6efe0; color: #1a1a1a; }
    h1 { color: #b3161b; margin: 0 0 .5rem; }
    a { color: #b3161b; font-weight: 600; }
    html.tema-escuro body { background: #15131a; color: #ece8df; }
    html.tema-escuro h1, html.tema-escuro a { color: #e7b65c; }
</style>
<script src="assets/js/acessibilidade.js"></script>
</head>
<body>
    <h1>Ops!</h1>
    <p><?= htmlspecialchars($mensagem) ?></p>
    <p><a href="/foodhub/public/dashboard.php">Ir para o início</a> · <a href="/foodhub/public/logout.php">Sair e entrar com outro usuário</a></p>
</body>
</html>
