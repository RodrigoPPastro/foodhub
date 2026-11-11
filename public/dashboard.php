<?php
/**
 * RF25 — Dashboard (Master). Os números vêm de api/dashboard.php (Fetch); aqui só fica o esqueleto.
 * O cliente comum não vê dados financeiros: é enviado ao cardápio (RF01).
 */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/SessionService.php';

require_once __DIR__ . '/../src/Services/PermissaoService.php';

AuthGuard::requireLogin();
if (PermissaoService::ehCliente()) {
    header('Location: cardapio.php');
    exit;
}
if (!PermissaoService::pode('dashboard')) {
    // Funcionário sem acesso ao dashboard: vai para a primeira tela liberada
    $inicio = PermissaoService::paginaInicial();
    header('Location: ' . ($inicio ?? 'erro.php?tipo=sem_permissoes'));
    exit;
}

$layoutTitulo = 'Dashboard';
$layoutAtivo  = 'dashboard';
$layoutHead   = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Dashboard</h1><p>Visão do dia e dos últimos 7 dias. <span id="atualizadoEm">Carregando…</span></p></div>
    <div class="acoes"><button type="button" class="btn-fh-outline" id="btnAtualizar">Atualizar</button></div>
</div>

<div id="dashErro" class="fh-alert erro" role="alert" hidden></div>

<section class="fh-grid c4 mb-3" aria-label="Indicadores do dia">
    <div class="metric-card"><div class="metric-icon"><?= _icone('financeiro') ?></div><span>Vendas de hoje</span><strong id="kVendas">—</strong><small id="kVendasSub">&nbsp;</small></div>
    <div class="metric-card gold"><div class="metric-icon"><?= _icone('relatorios') ?></div><span>Ticket médio</span><strong id="kTicket">—</strong><small>por pedido válido</small></div>
    <div class="metric-card"><div class="metric-icon"><?= _icone('pedidos') ?></div><span>Pedidos em aberto</span><strong id="kAbertos">—</strong><small id="kAtrasados">&nbsp;</small></div>
    <div class="metric-card gold" id="cardEstoque"><div class="metric-icon"><?= _icone('estoque') ?></div><span>Estoque crítico</span><strong id="kEstoque">—</strong><small>insumos abaixo do mínimo</small></div>
</section>

<section class="fh-grid c4 mb-3" aria-label="Financeiro do mês">
    <a class="metric-card" href="contas.php" style="text-decoration:none;color:inherit"><span>Saldo do mês</span><strong id="kSaldo">—</strong><small>entradas − saídas pagas</small></a>
    <a class="metric-card gold" href="contas.php" style="text-decoration:none;color:inherit"><span>A receber</span><strong id="kReceber">—</strong><small id="kReceberSub">&nbsp;</small></a>
    <a class="metric-card gold" href="contas.php" style="text-decoration:none;color:inherit"><span>A pagar</span><strong id="kPagar">—</strong><small id="kPagarSub">&nbsp;</small></a>
    <a class="metric-card" id="cardContas" href="contas.php" style="text-decoration:none;color:inherit"><span>Contas a pagar vencidas</span><strong id="kVencidas">—</strong><small id="kVencidasSub">&nbsp;</small></a>
</section>

<div class="fh-grid c-8-4 mb-3">
    <div class="panel"><div class="panel-heading"><h2>Vendas por dia</h2><span>últimos 7 dias</span></div>
        <div class="chart-box" id="boxVendas"><canvas id="grafVendas" aria-label="Gráfico de vendas por dia" role="img"></canvas><div class="chart-vazio">Sem vendas no período.</div></div></div>
    <div class="panel"><div class="panel-heading"><h2>Pedidos de hoje por status</h2></div>
        <div class="status-barras" id="barrasStatus"></div></div>
</div>

<div class="fh-grid c3 mb-3">
    <div class="panel"><div class="panel-heading"><h2>Vendas por canal</h2></div>
        <div class="chart-box" id="boxCanais"><canvas id="grafCanais" role="img" aria-label="Vendas por canal"></canvas><div class="chart-vazio">Sem dados.</div></div></div>
    <div class="panel"><div class="panel-heading"><h2>Formas de pagamento</h2></div>
        <div class="chart-box" id="boxFormas"><canvas id="grafFormas" role="img" aria-label="Formas de pagamento"></canvas><div class="chart-vazio">Sem dados.</div></div></div>
    <div class="panel"><div class="panel-heading"><h2>Produtos mais vendidos</h2></div>
        <div class="chart-box" id="boxProdutos"><canvas id="grafProdutos" role="img" aria-label="Produtos mais vendidos"></canvas><div class="chart-vazio">Sem dados.</div></div></div>
</div>

<div class="fh-grid c2 mb-3">
    <div class="panel"><div class="panel-heading"><h2>Últimos pedidos</h2><a href="pedidos.php">ver quadro</a></div>
        <ul class="lista-simples" id="listaUltimos"></ul></div>
    <div class="panel"><div class="panel-heading"><h2>Estoque abaixo do mínimo</h2><a href="estoque.php">ver estoque</a></div>
        <ul class="lista-simples" id="listaEstoque"></ul></div>
</div>

<div class="fh-grid c4">
    <a class="btn-fh-outline" href="pedidos.php">Quadro de pedidos</a>
    <a class="btn-fh-outline" href="fechamento_caixa.php">Fechamento de caixa</a>
    <a class="btn-fh-outline" href="contas.php">Contas a pagar/receber</a>
    <a class="btn-fh-outline" href="relatorios.php">Relatórios</a>
    <a class="btn-fh-outline" href="produtos.php">Produtos</a>
</div>
<?php
$layoutScripts = ['assets/js/dashboard.js'];
require __DIR__ . '/_layout_fim.php';
