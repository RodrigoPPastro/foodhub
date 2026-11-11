<?php
/** Histórico de pedidos finalizados (entregues e cancelados): busca, filtros, totais e reimpressão da comanda. */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('pedidos');
$layoutTitulo = 'Histórico de pedidos';
$layoutAtivo  = 'historico';
require __DIR__ . '/_layout.php';
$hoje = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
?>
<div class="page-heading">
    <div><h1>Histórico de pedidos</h1><p>Pedidos entregues e cancelados. Busque, filtre e reimprima a comanda.</p></div>
    <div class="acoes"><a href="pedidos.php" class="btn-fh-outline" style="text-decoration:none">← Voltar ao quadro</a></div>
</div>
<div class="fh-card-inner">
    <form class="fh-toolbar mb-2" id="formHist" novalidate>
        <div class="fh-form-group" style="flex:1;min-width:200px"><label for="hBusca">Buscar</label>
            <input class="fh-input" id="hBusca" placeholder="Nº do pedido, nº da mesa, cliente ou código iFood…"></div>
        <div class="fh-form-group"><label for="hDe">De</label><input class="fh-input" type="date" id="hDe" value="<?= h($hoje) ?>"></div>
        <div class="fh-form-group"><label for="hAte">Até</label><input class="fh-input" type="date" id="hAte" value="<?= h($hoje) ?>"></div>
        <div class="fh-form-group"><label for="hCanal">Canal</label><select class="fh-select" id="hCanal"><option value="">Todos</option></select></div>
        <div class="fh-form-group"><label for="hStatus">Situação</label>
            <select class="fh-select" id="hStatus"><option value="">Todos</option><option value="entregue">Entregues</option><option value="cancelado">Cancelados</option></select></div>
        <button type="submit" class="btn-fh">Filtrar</button>
    </form>
    <div class="hist-resumo" id="hResumo"></div>
    <div class="table-wrap"><table class="fh-table">
        <thead><tr><th>Pedido</th><th>Data/hora</th><th>Canal / mesa</th><th>Cliente</th><th>Itens</th><th class="num">Total</th><th>Pagamento</th><th>Situação</th><th></th></tr></thead>
        <tbody id="tbHist"><tr><td colspan="9" class="vazio-msg">Carregando…</td></tr></tbody>
        <tfoot id="tfHist"></tfoot>
    </table></div>
</div>
<?php
$layoutScripts = ['assets/js/comanda.js', 'assets/js/historico.js'];
require __DIR__ . '/_layout_fim.php';
