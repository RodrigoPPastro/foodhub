<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/PeriodoService.php';

AuthGuard::requireMaster();

$tituloPagina = 'Relatórios';
$paginaAtiva  = 'relatorios.php';
$hoje   = PeriodoService::hoje();
$inicio = (new DateTimeImmutable($hoje))->modify('-29 days')->format('Y-m-d');

require __DIR__ . '/partials/topo_financeiro.php';
?>
    <h1 class="h3 mb-4">Relatórios gerenciais</h1>

    <form id="formFiltro" class="row g-2 align-items-end mb-4">
        <div class="col-6 col-sm-auto"><label for="inicio" class="form-label mb-1">De</label>
            <input type="date" id="inicio" class="form-control" value="<?= htmlspecialchars($inicio) ?>" required></div>
        <div class="col-6 col-sm-auto"><label for="fim" class="form-label mb-1">Até</label>
            <input type="date" id="fim" class="form-control" value="<?= htmlspecialchars($hoje) ?>" required></div>
        <div class="col-6 col-sm-auto"><label for="limite" class="form-label mb-1">Top produtos</label>
            <select id="limite" class="form-select"><option>5</option><option selected>10</option><option>20</option></select></div>
        <div class="col-auto"><button type="submit" class="btn btn-fh" id="btnFiltrar">Aplicar</button></div>
    </form>

    <ul class="nav nav-tabs" id="abas" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#abaVendas" type="button" role="tab">Vendas por período</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaProdutos" type="button" role="tab">Mais vendidos</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaInsumos" type="button" role="tab">Consumo de insumos</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaTicket" type="button" role="tab">Ticket médio</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#abaCanais" type="button" role="tab">Comparativo de canais</button></li>
    </ul>

    <div class="tab-content fh-cartao border-top-0 rounded-top-0">
        <div class="tab-pane fade show active" id="abaVendas" role="tabpanel">
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Dia</th><th class="num">Pedidos</th><th class="num">Total</th></tr></thead>
                <tbody id="tbVendas"></tbody><tfoot id="tfVendas"></tfoot></table></div>
        </div>
        <div class="tab-pane fade" id="abaProdutos" role="tabpanel">
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>#</th><th>Produto</th><th class="num">Unidades</th><th class="num">Receita</th></tr></thead>
                <tbody id="tbProdutos"></tbody></table></div>
        </div>
        <div class="tab-pane fade" id="abaInsumos" role="tabpanel">
            <p class="small text-secondary">Consumo estimado: itens vendidos × ficha técnica de cada produto.</p>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Insumo</th><th class="num">Consumo</th><th class="num">Custo</th></tr></thead>
                <tbody id="tbInsumos"></tbody><tfoot id="tfInsumos"></tfoot></table></div>
        </div>
        <div class="tab-pane fade" id="abaTicket" role="tabpanel">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4"><div class="fh-cartao"><div class="fh-kpi-rotulo">Ticket médio do período</div><div class="fh-kpi-valor" id="tkMedio">—</div></div></div>
                <div class="col-6 col-md-4"><div class="fh-cartao ouro"><div class="fh-kpi-rotulo">Pedidos</div><div class="fh-kpi-valor" id="tkPedidos">—</div></div></div>
                <div class="col-6 col-md-4"><div class="fh-cartao ouro"><div class="fh-kpi-rotulo">Faturamento</div><div class="fh-kpi-valor" id="tkTotal">—</div></div></div>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Dia</th><th class="num">Pedidos</th><th class="num">Ticket médio</th></tr></thead>
                <tbody id="tbTicket"></tbody></table></div>
        </div>
        <div class="tab-pane fade" id="abaCanais" role="tabpanel">
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Canal</th><th class="num">Pedidos</th><th class="num">Total</th><th class="num">Ticket médio</th>
                    <th style="min-width:160px">Participação</th><th class="num">Dif. vs líder (R$)</th><th class="num">Dif. vs líder (%)</th></tr></thead>
                <tbody id="tbCanais"></tbody><tfoot id="tfCanais"></tfoot></table></div>
        </div>
    </div>

<?php require __DIR__ . '/partials/rodape_financeiro.php'; ?>
<script src="assets/js/relatorios.js"></script>
</body>
</html>
