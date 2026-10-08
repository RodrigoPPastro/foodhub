<?php
/**
 * Página TEMPORÁRIA para testar a barra de acessibilidade (RF27).
 * Não faz parte do sistema: apagar antes do merge final, ou manter
 * apenas enquanto o grupo não tiver o layout compartilhado.
 */
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teste — Acessibilidade (RF27)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- RF27: estas duas linhas são a integração completa -->
    <link rel="stylesheet" href="/foodhub/public/assets/css/acessibilidade.css">
    <script src="/foodhub/public/assets/js/acessibilidade.js"></script>
</head>
<body class="p-4">
    <main class="container" style="max-width: 720px;">
        <img src="/foodhub/public/assets/img/logo-foodhub.png" alt="FOOD HUB" style="max-height: 90px;" class="mb-3">

        <h1>Página de teste</h1>
        <p class="lead">Use a barra no canto inferior direito: aumente a fonte e ative o contraste.</p>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5 card-title">Cartão de exemplo</h2>
                <p class="card-text">Texto comum, com um <a href="#">link de exemplo</a> no meio da frase.</p>
                <div class="mb-3">
                    <label for="campo" class="form-label">Campo de texto</label>
                    <input type="text" id="campo" class="form-control" placeholder="Digite algo">
                </div>
                <button type="button" class="btn btn-danger">Botão principal</button>
                <button type="button" class="btn btn-outline-secondary">Secundário</button>
            </div>
        </div>

        <div class="alert alert-warning" role="alert">Aviso de exemplo (alerta do Bootstrap).</div>
    </main>
</body>
</html>
