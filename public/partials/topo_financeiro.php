<?php
/**
 * Topo comum das telas do módulo financeiro.
 * Variáveis esperadas: $tituloPagina (string) e $paginaAtiva (nome do arquivo).
 *
 * NOTA: este cabeçalho é PROVISÓRIO. A Dandara é a dona do header/menu
 * padrão (RF10/RF11) — quando ele existir, basta substituir este partial.
 * Idem a barra de acessibilidade (RF27, já na main): incluir logo após <body>.
 */
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$menu = [
    'dashboard.php'        => 'Dashboard',
    'fechamento_caixa.php' => 'Fechamento de Caixa',
    'relatorios.php'       => 'Relatórios',
];
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FOOD HUB — <?= $h($tituloPagina) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/financeiro.css" rel="stylesheet">
    <script>
        // Aplica o tema salvo antes de pintar a página (evita "piscar" claro→escuro).
        try { document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('foodhub-tema') === 'dark' ? 'dark' : 'light'); } catch (e) {}
    </script>
</head>
<body>
<nav class="navbar navbar-expand-md fh-nav" aria-label="Navegação principal">
    <div class="container-xl">
        <a class="navbar-brand fh-titulo" href="dashboard.php">FOOD HUB</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal"
                aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav me-auto">
                <?php foreach ($menu as $arquivo => $rotulo): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $arquivo === $paginaAtiva ? 'active' : '' ?>"
                           href="<?= $h($arquivo) ?>" <?= $arquivo === $paginaAtiva ? 'aria-current="page"' : '' ?>><?= $h($rotulo) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-2 fh-usuario">
                <span><?= $h(SessionService::nomeUsuarioAtual()) ?></span>
                <button type="button" id="alternarTema" class="btn btn-sm btn-outline-light" aria-label="Alternar entre modo claro e escuro">Modo escuro</button>
                <a class="btn btn-sm btn-fh" href="logout.php">Sair</a>
            </div>
        </div>
    </div>
</nav>
<main class="container-xl py-4">
