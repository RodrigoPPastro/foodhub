<?php
/**
 * Layout compartilhado por TODAS as páginas autenticadas (RF10 / RF11).
 *
 *   $layoutTitulo = 'Título da Página';
 *   $layoutAtivo  = 'pedidos';          // chave do item de menu ativo
 *   $layoutHead   = '<link ...>';       // (opcional) HTML extra no <head>
 *   require __DIR__ . '/_layout.php';
 *   // ... conteúdo ...
 *   $layoutScripts = ['assets/js/minha_pagina.js'];   // (opcional)
 *   require __DIR__ . '/_layout_fim.php';
 *
 * Exige que a página já tenha chamado AuthGuard::requireLogin()/requireMaster().
 */

require_once __DIR__ . '/../src/Services/SessionService.php';
require_once __DIR__ . '/../src/Services/PermissaoService.php';

if (!isset($layoutTitulo)) $layoutTitulo = 'FOOD HUB';
if (!isset($layoutAtivo))  $layoutAtivo  = '';
if (!isset($layoutHead))   $layoutHead   = '';

date_default_timezone_set('America/Sao_Paulo');

$_nomeUsuario  = SessionService::nomeUsuarioAtual() ?: 'Usuário';
$nomeSeguro    = htmlspecialchars($_nomeUsuario, ENT_QUOTES, 'UTF-8');
$ehMaster      = SessionService::ehMaster();
$ehFuncionario = PermissaoService::ehFuncionario();
$perfilSeguro  = htmlspecialchars($ehMaster ? 'Master' : ($ehFuncionario ? 'Funcionário' : 'Cliente'), ENT_QUOTES, 'UTF-8');

// Esconde na tela os botões que o usuário não pode usar no módulo desta página.
// (Só visual: o servidor bloqueia de qualquer jeito — ver ApiGuard::requirePermissao.)
$_classesPerm = [];
if (Permissao::existe($layoutAtivo)) {
    foreach (['incluir', 'alterar', 'excluir'] as $_acao) {
        if (!PermissaoService::pode($layoutAtivo, $_acao)) {
            $_classesPerm[] = 'perm-sem-' . $_acao;
        }
    }
}
$primeiraLetra = htmlspecialchars(
    function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($_nomeUsuario, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($_nomeUsuario, 0, 1)),
    ENT_QUOTES, 'UTF-8'
);

if (!function_exists('_icone')) {
    function _icone(string $nome): string
    {
        $ic = [
            'dashboard'  => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
            'pedidos'    => '<path d="M6 2h12l3 5v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7l3-5z"/><path d="M3 7h18M8 11h8M8 15h8"/>',
            'cozinha'    => '<path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/><line x1="6" y1="17" x2="18" y2="17"/>',
            'historico'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'bar'        => '<path d="M8 22h8M12 15v7M5 3h14l-7 12z"/><path d="M7.5 7h9"/>',
            'mesas'      => '<ellipse cx="12" cy="8" rx="9" ry="3"/><path d="M12 11v10M7 21h10"/>',
            'entregador' => '<circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h-3l-3 6 3.5 5.5M12 17.5h-6.5M15 6l3.5 11.5M14 3h3"/>',
            'cardapio'   => '<path d="M4 3h16v18l-3-2-2.5 2-2.5-2-2.5 2L7 19l-3 2z"/><path d="M8 8h8M8 12h8"/>',
            'produtos'   => '<path d="m12 3 9 5-9 5-9-5 9-5z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>',
            'categorias' => '<path d="M4 4h7v7H4zM15 4h5v5h-5zM15 13h5v7h-5zM4 15h7v5H4z"/>',
            'estoque'    => '<path d="M3 7h18v14H3z"/><path d="M3 7l3-4h12l3 4M12 11v6M9 14h6"/>',
            'financeiro' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>',
            'relatorios' => '<path d="M3 3v18h18"/><path d="m7 14 4-4 4 3 6-7"/>',
            'usuarios'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 8v6M17 11h6"/>',
            'auditoria'  => '<path d="M12 3 4 6v5c0 5 3.5 8 8 10 4.5-2 8-5 8-10V6l-8-3z"/><path d="m9 12 2 2 4-4"/>',
            'integracao' => '<path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3A5 5 0 0 0 11 21.07l1.5-1.5"/>',
            'banco'      => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
            'senha'      => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'sair'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
            'menu'       => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'acessib'    => '<circle cx="12" cy="4.5" r="2"/><path d="M5 8.5 12 10l7-1.5M12 10v5l-3 6M12 15l3 6"/>',
            'download'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
            'calendario' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
            'grafico'    => '<path d="M3 3v18h18"/><path d="m7 14 4-4 4 3 6-7"/>',
            'mais'       => '<path d="M12 5v14M5 12h14"/>',
            'seta'       => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'carrinho'   => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
            'alerta'     => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
            'relogio'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        ];
        $c = $ic[$nome] ?? $ic['dashboard'];
        return '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $c . '</svg>';
    }
}

if (!function_exists('_navItem')) {
    function _navItem(string $href, string $label, string $icone, string $atual, string $chave): string
    {
        $ativo = ($atual === $chave);
        return '<a href="' . $href . '" class="nav-item' . ($ativo ? ' active' : '') . '" title="' . $label . '"'
            . ($ativo ? ' aria-current="page"' : '') . '>' . _icone($icone) . '<span>' . $label . '</span></a>';
    }
}

if (!function_exists('h')) {
    /** Escapa texto para HTML (usar em TODA saída vinda do banco/usuário). */
    function h($valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($layoutTitulo) ?> | FOOD HUB</title>
    <link rel="icon" href="assets/img/logo-foodhub.png">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <script>window.FH_PERFIL = <?= json_encode(SessionService::perfilAtual()) ?>; window.FH_PERM = <?= json_encode(PermissaoService::paraNavegador()) ?>;</script>
    <?= $layoutHead ?>
</head>
<body class="<?= h(implode(' ', $_classesPerm)) ?>">

<!-- RF27: barra de acessibilidade (contraste + tamanho da fonte) -->
<div class="acessib-bar" role="toolbar" aria-label="Opções de acessibilidade">
    <span class="ab-label"><?= _icone('acessib') ?> Acessibilidade:</span>
    <button type="button" id="btnContraste" aria-pressed="false" title="Alternar alto contraste">Alto contraste</button>
    <button type="button" id="btnFonteM" aria-label="Diminuir fonte" title="Diminuir fonte">A−</button>
    <button type="button" id="btnFonteR" aria-label="Tamanho padrão da fonte" title="Restaurar fonte">A</button>
    <button type="button" id="btnFonteP" aria-label="Aumentar fonte" title="Aumentar fonte">A+</button>
</div>

<div class="app-container" id="appContainer">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <img src="assets/img/logo-foodhub.png" alt="FOOD HUB" class="brand-logo">
            <div class="brand-mark"><?= _icone('produtos') ?></div>
        </div>

        <nav class="sidebar-nav" aria-label="Navegação principal">
            <?php if ($ehMaster || $ehFuncionario): ?>
            <?php
            // Cada item só aparece se o usuário puder VER a tela (o Master vê tudo)
            $_grupos = [
                'VISÃO GERAL'        => [['dashboard', 'dashboard.php', 'Dashboard', 'dashboard', 'dashboard']],
                'OPERAÇÃO'           => [['pedidos', 'pedidos.php', 'Pedidos', 'pedidos', 'pedidos'],
                                         ['pedidos', 'historico.php', 'Histórico', 'historico', 'historico'],
                                         ['cozinha', 'cozinha.php', 'Cozinha', 'cozinha', 'cozinha'],
                                         ['bar', 'bar.php', 'Bar', 'bar', 'bar'],
                                         ['mesas', 'mesas.php', 'Mesas', 'mesas', 'mesas'],
                                         ['entregadores', 'entregadores.php', 'Entregadores', 'entregador', 'entregadores']],
                'CARDÁPIO E ESTOQUE' => [[null, 'cardapio.php', 'Cardápio', 'cardapio', 'cardapio'],
                                         ['produtos', 'produtos.php', 'Produtos', 'produtos', 'produtos'],
                                         ['categorias', 'categorias.php', 'Categorias', 'categorias', 'categorias'],
                                         ['estoque', 'estoque.php', 'Estoque', 'estoque', 'estoque']],
                'GESTÃO'             => [['caixa', 'fechamento_caixa.php', 'Fechamento de caixa', 'financeiro', 'caixa'],
                                         ['contas', 'contas.php', 'Contas a pagar/receber', 'calendario', 'contas'],
                                         ['relatorios', 'relatorios.php', 'Relatórios', 'relatorios', 'relatorios']],
            ];
            foreach ($_grupos as $_titulo => $_itens):
                $_html = '';
                foreach ($_itens as [$_mod, $_href, $_rot, $_ic, $_chave]) {
                    if ($_mod === null || PermissaoService::pode($_mod)) {
                        $_html .= _navItem($_href, $_rot, $_ic, $layoutAtivo, $_chave);
                    }
                }
                if ($_html === '') { continue; }
            ?>
            <div class="nav-group">
                <span class="nav-section-title"><?= h($_titulo) ?></span>
                <?= $_html ?>
            </div>
            <?php endforeach; ?>
            <?php if ($ehMaster): ?>
            <div class="nav-group">
                <span class="nav-section-title">ADMINISTRAÇÃO</span>
                <?= _navItem('consulta_usuario.php', 'Usuários', 'usuarios', $layoutAtivo, 'consulta') ?>
                <?= _navItem('log_acesso.php', 'Auditoria', 'auditoria', $layoutAtivo, 'log') ?>
                <?= _navItem('integracoes.php', 'Integrações', 'integracao', $layoutAtivo, 'integracoes') ?>
                <?= _navItem('modelo_bd.php', 'Modelo do BD', 'banco', $layoutAtivo, 'modelo') ?>
            </div>
            <?php else: ?>
            <div class="nav-group">
                <span class="nav-section-title">CONTA</span>
                <?= _navItem('alterar_senha.php', 'Alterar senha', 'senha', $layoutAtivo, 'senha') ?>
                <?= _navItem('modelo_bd.php', 'Modelo do BD', 'banco', $layoutAtivo, 'modelo') ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="nav-group">
                <span class="nav-section-title">PEDIDOS</span>
                <?= _navItem('cardapio.php', 'Cardápio', 'cardapio', $layoutAtivo, 'cardapio') ?>
                <?= _navItem('meus_pedidos.php', 'Meus pedidos', 'pedidos', $layoutAtivo, 'meus_pedidos') ?>
            </div>
            <div class="nav-group">
                <span class="nav-section-title">CONTA</span>
                <?= _navItem('alterar_senha.php', 'Alterar senha', 'senha', $layoutAtivo, 'senha') ?>
                <?= _navItem('modelo_bd.php', 'Modelo do BD', 'banco', $layoutAtivo, 'modelo') ?>
            </div>
            <?php endif; ?>
        </nav>

        <div class="sidebar-bottom">
            <div class="system-status" title="Sistema operacional"><span class="status-dot"></span><span>Sistema operacional</span></div>
            <a href="logout.php" class="logout-link" title="Sair do sistema"><?= _icone('sair') ?><span>Sair do sistema</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Abrir menu" aria-expanded="false">
                <?= _icone('menu') ?>
            </button>
            <div class="welcome">
                <span>FOOD HUB</span>
                <strong><?= h($layoutTitulo) ?></strong>
            </div>
            <!-- RF10: login do usuário autenticado no canto superior direito, com logout -->
            <div class="user-area">
                <div class="user-avatar"><?= $primeiraLetra ?></div>
                <div class="user-info">
                    <strong><?= $nomeSeguro ?></strong>
                    <span><?= $perfilSeguro ?></span>
                </div>
                <a href="logout.php" class="topbar-logout" title="Sair" aria-label="Sair do sistema"><?= _icone('sair') ?></a>
            </div>
        </header>

        <section class="dashboard-content">
