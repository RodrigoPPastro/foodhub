<?php
/** RF24 — relatórios gerenciais (Master). Filtros por AJAX (RNF08). Dados via api/relatorios.php. */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/PeriodoService.php';

AuthGuard::requirePermissao('relatorios');

$hoje   = PeriodoService::hoje();
$inicio = (new DateTimeImmutable($hoje))->modify('-29 days')->format('Y-m-d');

$layoutTitulo = 'Relatórios';
$layoutAtivo  = 'relatorios';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading"><div><h1>Relatórios gerenciais</h1><p>Vendas, produtos, insumos, ticket médio e canais no período escolhido.</p></div></div>

<div class="fh-card-inner">
    <form id="formFiltro" class="fh-toolbar">
        <div class="fh-form-group"><label for="inicio">De</label><input type="date" id="inicio" class="fh-input" value="<?= h($inicio) ?>" required></div>
        <div class="fh-form-group"><label for="fim">Até</label><input type="date" id="fim" class="fh-input" value="<?= h($hoje) ?>" required></div>
        <div class="fh-form-group"><label for="limite">Top produtos</label><select id="limite" class="fh-select"><option>5</option><option selected>10</option><option>20</option></select></div>
        <button type="submit" class="btn-fh" id="btnFiltrar">Aplicar</button>
    </form>
</div>

<div id="abas">
    <div class="fh-tabs" role="tablist">
        <button class="fh-tab ativa" data-aba="vendas" role="tab" aria-selected="true">Vendas por período</button>
        <button class="fh-tab" data-aba="produtos" role="tab" aria-selected="false">Mais vendidos</button>
        <button class="fh-tab" data-aba="insumos" role="tab" aria-selected="false">Consumo de insumos</button>
        <button class="fh-tab" data-aba="ticket" role="tab" aria-selected="false">Ticket médio</button>
        <button class="fh-tab" data-aba="canais" role="tab" aria-selected="false">Comparativo de canais</button>
    </div>
    <div class="fh-card-inner">
        <div data-painel="vendas" class="fh-tab-painel"><div class="table-wrap"><table class="fh-table">
            <thead><tr><th>Dia</th><th class="num">Pedidos</th><th class="num">Total</th></tr></thead><tbody id="tbVendas"></tbody><tfoot id="tfVendas"></tfoot></table></div></div>
        <div data-painel="produtos" class="fh-tab-painel" hidden><div class="table-wrap"><table class="fh-table">
            <thead><tr><th>#</th><th>Produto</th><th class="num">Unidades</th><th class="num">Receita</th></tr></thead><tbody id="tbProdutos"></tbody></table></div></div>
        <div data-painel="insumos" class="fh-tab-painel" hidden>
            <p class="fh-hint">Consumo estimado: itens vendidos × ficha técnica de cada produto.</p>
            <div class="table-wrap"><table class="fh-table">
            <thead><tr><th>Insumo</th><th class="num">Consumo</th><th class="num">Custo</th></tr></thead><tbody id="tbInsumos"></tbody><tfoot id="tfInsumos"></tfoot></table></div></div>
        <div data-painel="ticket" class="fh-tab-painel" hidden>
            <div class="fh-grid c3 mb-2">
                <div class="metric-card"><span>Ticket médio do período</span><strong id="tkMedio">—</strong></div>
                <div class="metric-card gold"><span>Pedidos</span><strong id="tkPedidos">—</strong></div>
                <div class="metric-card gold"><span>Faturamento</span><strong id="tkTotal">—</strong></div>
            </div>
            <div class="table-wrap"><table class="fh-table">
            <thead><tr><th>Dia</th><th class="num">Pedidos</th><th class="num">Ticket médio</th></tr></thead><tbody id="tbTicket"></tbody></table></div></div>
        <div data-painel="canais" class="fh-tab-painel" hidden><div class="table-wrap"><table class="fh-table">
            <thead><tr><th>Canal</th><th class="num">Pedidos</th><th class="num">Total</th><th class="num">Ticket médio</th><th style="min-width:160px">Participação</th><th class="num">Dif. vs líder (R$)</th><th class="num">Dif. vs líder (%)</th></tr></thead>
            <tbody id="tbCanais"></tbody><tfoot id="tfCanais"></tfoot></table></div></div>
    </div>
</div>
<?php
$layoutScripts = ['assets/js/relatorios.js'];
require __DIR__ . '/_layout_fim.php';
