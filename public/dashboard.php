<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';

/**
 * RF25 — Dashboard (Master).
 * O login/2FA/recuperação de senha redirecionam TODOS os perfis para esta página.
 * O cliente (perfil comum) não vê dados financeiros: é reenviado à página inicial
 * dele. Combinar com a Dandara o nome real da tela do cardápio (RF01).
 */
const PAGINA_INICIAL_COMUM = 'cardapio.php';

AuthGuard::requireLogin();
if (!SessionService::ehMaster()) {
    header('Location: ' . PAGINA_INICIAL_COMUM);
    exit;
}

$tituloPagina = 'Dashboard';
$paginaAtiva  = 'dashboard.php';
require __DIR__ . '/partials/topo_financeiro.php';
?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 m-0">Dashboard</h1>
        <div class="d-flex align-items-center gap-2">
            <small class="text-secondary" id="atualizadoEm">Carregando…</small>
            <button type="button" class="btn btn-sm btn-fh" id="btnAtualizar">Atualizar</button>
        </div>
    </div>

    <!-- Indicadores -->
    <section class="row g-3 mb-4" aria-label="Indicadores do dia">
        <div class="col-6 col-lg-3">
            <div class="fh-cartao">
                <div class="fh-kpi-rotulo">Vendas do dia</div>
                <div class="fh-kpi-valor" id="kpiVendas">—</div>
                <div class="fh-kpi-detalhe" id="kpiVendasPedidos">&nbsp;</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="fh-cartao ouro">
                <div class="fh-kpi-rotulo">Pedidos em aberto</div>
                <div class="fh-kpi-valor" id="kpiAbertos">—</div>
                <div class="fh-kpi-detalhe">novo, em preparo, pronto ou em entrega</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="fh-cartao">
                <div class="fh-kpi-rotulo">Ticket médio (hoje)</div>
                <div class="fh-kpi-valor" id="kpiTicket">—</div>
                <div class="fh-kpi-detalhe">vendas ÷ pedidos</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="fh-cartao ouro" id="cartaoEstoque">
                <div class="fh-kpi-rotulo">Estoque baixo</div>
                <div class="fh-kpi-valor" id="kpiEstoque">—</div>
                <div class="fh-kpi-detalhe">insumos abaixo do mínimo</div>
            </div>
        </div>
    </section>

    <!-- Gráficos (últimos 7 dias) -->
    <section class="row g-3 mb-4" aria-label="Gráficos de vendas dos últimos 7 dias">
        <div class="col-12 col-lg-8">
            <div class="fh-cartao">
                <h2 class="h6 mb-3">Vendas por dia — últimos 7 dias</h2>
                <div class="fh-grafico" id="boxVendas">
                    <canvas id="grafVendas" role="img" aria-label="Gráfico de barras com as vendas diárias dos últimos 7 dias"></canvas>
                    <div class="fh-vazio">Sem vendas no período.</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="fh-cartao">
                <h2 class="h6 mb-3">Vendas por canal</h2>
                <div class="fh-grafico" id="boxCanais">
                    <canvas id="grafCanais" role="img" aria-label="Gráfico de rosca com as vendas por canal"></canvas>
                    <div class="fh-vazio">Sem vendas no período.</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="fh-cartao">
                <h2 class="h6 mb-3">Formas de pagamento</h2>
                <div class="fh-grafico" id="boxFormas">
                    <canvas id="grafFormas" role="img" aria-label="Gráfico de rosca com o total por forma de pagamento"></canvas>
                    <div class="fh-vazio">Sem pagamentos no período.</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="fh-cartao">
                <h2 class="h6 mb-3">Produtos mais vendidos (unidades)</h2>
                <div class="fh-grafico" id="boxProdutos">
                    <canvas id="grafProdutos" role="img" aria-label="Gráfico de barras horizontais com os 5 produtos mais vendidos"></canvas>
                    <div class="fh-vazio">Sem vendas no período.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Lista de estoque baixo (RF16) -->
    <section class="fh-cartao" aria-label="Itens com estoque baixo">
        <h2 class="h6 mb-3">Itens com estoque abaixo do mínimo</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Insumo</th><th class="num">Atual</th><th class="num">Mínimo</th></tr></thead>
                <tbody id="tbEstoque"><tr><td colspan="3" class="text-secondary">Carregando…</td></tr></tbody>
            </table>
        </div>
    </section>

<?php require __DIR__ . '/partials/rodape_financeiro.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
