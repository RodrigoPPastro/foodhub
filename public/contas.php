<?php
/**
 * Financeiro — contas a pagar e a receber, repasse de plataformas e fluxo de caixa (Master).
 * Dados via api/contas.php. O fechamento de caixa (vendas do dia) continua em sua tela própria.
 */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('contas');
$layoutTitulo = 'Contas a pagar e a receber';
$layoutAtivo  = 'contas';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Contas a pagar e a receber</h1><p>O que vai sair, o que ainda vai entrar (como o repasse do iFood) e o saldo do período.</p></div>
    <div class="acoes"><button type="button" class="btn-fh" id="btnNovaConta">+ Nova conta</button></div>
</div>

<!-- Fluxo de caixa do período -->
<div class="fh-card-inner">
    <form class="fh-toolbar" id="formFluxo">
        <div class="fh-form-group"><label for="fxDe">De</label><input type="date" class="fh-input" id="fxDe"></div>
        <div class="fh-form-group"><label for="fxAte">Até</label><input type="date" class="fh-input" id="fxAte"></div>
        <button type="submit" class="btn-fh-outline">Atualizar fluxo</button>
    </form>
    <div class="fh-grid c4" style="margin-top:1rem">
        <div class="metric-card"><span>Entradas (vendas + recebimentos)</span><strong id="fxEntradas">—</strong><small id="fxEntradasSub">&nbsp;</small></div>
        <div class="metric-card gold"><span>Saídas pagas</span><strong id="fxSaidas">—</strong><small>contas a pagar baixadas</small></div>
        <div class="metric-card" id="fxSaldoCard"><span>Saldo do período</span><strong id="fxSaldo">—</strong><small>entradas − saídas</small></div>
        <div class="metric-card gold"><span>Projeção</span><strong id="fxProj">—</strong><small id="fxProjSub">&nbsp;</small></div>
    </div>
    <p class="fh-hint">Vendas de plataformas (iFood/Rappi) só contam como entrada quando o repasse é baixado — é a data em que o dinheiro realmente cai. Vendas do balcão, mesa e delivery próprio contam no dia do pagamento.</p>
</div>

<div class="fh-tabs" id="abasTipo" role="tablist">
    <button class="fh-tab ativa" data-tipo="pagar" role="tab" aria-selected="true">A pagar</button>
    <button class="fh-tab" data-tipo="receber" role="tab" aria-selected="false">A receber</button>
    <button class="fh-tab" data-tipo="canais" role="tab" aria-selected="false">Comissão das plataformas</button>
</div>

<!-- Listas -->
<div id="painelLista">
    <div class="fh-grid c3 mb-2">
        <div class="metric-card"><span id="resRotuloAberto">Em aberto</span><strong id="resAberto">—</strong></div>
        <div class="metric-card alerta" id="cardVencido"><span>Vencido</span><strong id="resVencido">—</strong><small id="resQtdVenc">&nbsp;</small></div>
        <div class="metric-card gold"><span>Soma da lista</span><strong id="resLista">—</strong></div>
    </div>
    <div class="fh-card-inner">
        <div class="fh-toolbar mb-2">
            <div class="fh-form-group"><label for="fSit">Situação</label>
                <select class="fh-select" id="fSit"><option value="aberta">Em aberto</option><option value="vencida">Vencidas</option><option value="baixada" id="optBaixada">Pagas</option><option value="cancelada">Canceladas</option><option value="todas">Todas</option></select></div>
            <div class="fh-form-group"><label for="fDe">Vencimento de</label><input type="date" class="fh-input" id="fDe"></div>
            <div class="fh-form-group"><label for="fAte">até</label><input type="date" class="fh-input" id="fAte"></div>
            <div class="fh-form-group"><label for="fBusca">Buscar</label><input class="fh-input" id="fBusca" placeholder="Descrição ou fornecedor…"></div>
            <button type="button" class="btn-fh-outline" id="btnLote" hidden>Baixar selecionadas</button>
        </div>
        <div class="table-wrap"><table class="fh-table">
            <thead><tr><th style="width:34px"><input type="checkbox" id="chkTodas" aria-label="Selecionar todas"></th><th>Vencimento</th><th>Descrição</th><th>Categoria</th><th class="num">Valor</th><th>Situação</th><th></th></tr></thead>
            <tbody id="tbContas"><tr><td colspan="7" class="vazio-msg">Carregando…</td></tr></tbody>
        </table></div>
    </div>
</div>

<!-- Comissão por plataforma -->
<div id="painelCanais" hidden>
    <div class="fh-card-inner">
        <h2>Comissão e prazo de repasse <small>valem para os próximos pedidos de cada plataforma</small></h2>
        <div class="table-wrap"><table class="fh-table">
            <thead><tr><th>Plataforma</th><th class="num" style="width:160px">Comissão (%)</th><th class="num" style="width:180px">Prazo de repasse (dias)</th><th></th></tr></thead>
            <tbody id="tbCanais"></tbody>
        </table></div>
        <p class="fh-hint">Exemplo: pedido de R$ 100,00 no iFood com 12% e 30 dias → conta a receber de R$ 88,00 vencendo daqui a 30 dias. Se o valor que cair for outro, ajuste na hora da baixa.</p>
    </div>
</div>

<!-- Nova / editar conta -->
<div class="fh-overlay" id="modalConta" role="dialog" aria-modal="true" aria-labelledby="tituloConta">
    <form class="fh-modal" id="formConta" novalidate>
        <h3 id="tituloConta">Nova conta</h3>
        <input type="hidden" id="ctId">
        <div class="fh-form-row">
            <div class="fh-form-group"><label for="ctTipo">Tipo *</label><select class="fh-select" id="ctTipo"><option value="pagar">A pagar</option><option value="receber">A receber</option></select></div>
            <div class="fh-form-group"><label for="ctCat">Categoria *</label><select class="fh-select" id="ctCat"></select></div>
        </div>
        <div class="fh-form-group"><label for="ctDesc">Descrição *</label><input class="fh-input" id="ctDesc" maxlength="150" placeholder="Ex.: Aluguel de novembro"></div>
        <div class="fh-form-group"><label for="ctPessoa" id="lblPessoa">Fornecedor</label><input class="fh-input" id="ctPessoa" maxlength="100"></div>
        <div class="fh-form-row">
            <div class="fh-form-group"><label for="ctValor">Valor (R$) *</label><input class="fh-input" id="ctValor" inputmode="decimal" placeholder="0,00"></div>
            <div class="fh-form-group"><label for="ctVenc">Vencimento *</label><input type="date" class="fh-input" id="ctVenc"></div>
        </div>
        <div class="fh-form-group"><label for="ctObs">Observação</label><input class="fh-input" id="ctObs" maxlength="255"></div>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalConta">Cancelar</button>
            <button type="submit" class="btn-fh">Salvar</button>
        </div>
    </form>
</div>

<!-- Dar baixa -->
<div class="fh-overlay" id="modalBaixa" role="dialog" aria-modal="true" aria-labelledby="tituloBaixa">
    <form class="fh-modal" id="formBaixa" novalidate>
        <h3 id="tituloBaixa">Dar baixa</h3>
        <p id="baixaDesc"></p>
        <input type="hidden" id="bxId">
        <div class="fh-form-row">
            <div class="fh-form-group"><label for="bxData">Data *</label><input type="date" class="fh-input" id="bxData"></div>
            <div class="fh-form-group"><label for="bxValor">Valor (R$) *</label><input class="fh-input" id="bxValor" inputmode="decimal"></div>
        </div>
        <p class="fh-hint">Se o valor que realmente foi pago/recebido for diferente do previsto, corrija aqui.</p>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalBaixa">Cancelar</button>
            <button type="submit" class="btn-fh">Confirmar baixa</button>
        </div>
    </form>
</div>
<?php
$layoutScripts = ['assets/js/contas.js'];
require __DIR__ . '/_layout_fim.php';
