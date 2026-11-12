<?php
/**
 * Taxa de serviço (10%) dos garçons (Master): quanto cada garçom tem a receber no período,
 * botão para PAGAR (marca como repassado) e cadastro dos garçons. Dados via api/gorjetas.php.
 */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('caixa');
$layoutTitulo = 'Taxa dos garçons';
$layoutAtivo  = 'gorjetas';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Taxa dos garçons (10%)</h1><p>Quanto cada garçom tem a receber. Ajuda a fechar o caixa e a registrar o repasse.</p></div>
    <div class="acoes"><button type="button" class="btn-fh-outline" id="btnGarcons">Garçons</button></div>
</div>

<div class="fh-card-inner">
    <form class="fh-toolbar" id="formPeriodo">
        <div class="fh-form-group"><label for="gDe">De</label><input type="date" class="fh-input" id="gDe"></div>
        <div class="fh-form-group"><label for="gAte">Até</label><input type="date" class="fh-input" id="gAte"></div>
        <button type="submit" class="btn-fh-outline">Atualizar</button>
    </form>
    <div class="fh-grid c3" style="margin-top:1rem">
        <div class="metric-card gold"><span>Taxa cobrada</span><strong id="gTotal">—</strong><small>no período</small></div>
        <div class="metric-card"><span>Já paga aos garçons</span><strong id="gPago">—</strong><small>&nbsp;</small></div>
        <div class="metric-card gold"><span>A pagar</span><strong id="gPend">—</strong><small>pendente de repasse</small></div>
    </div>
</div>

<section class="fh-card-inner"><h2>Por garçom</h2>
    <div class="table-wrap"><table class="fh-table">
        <thead><tr><th>Garçom</th><th class="num">Contas</th><th class="num">Total</th><th class="num">Pago</th><th class="num">A receber</th><th></th></tr></thead>
        <tbody id="tbGarcons"></tbody>
    </table></div>
</section>

<section class="fh-card-inner"><h2>Lançamentos</h2>
    <div class="table-wrap"><table class="fh-table">
        <thead><tr><th>Data/hora</th><th>Mesa</th><th>Garçom</th><th class="num">Conta</th><th class="num">Taxa</th><th>Situação</th></tr></thead>
        <tbody id="tbLanc"></tbody>
    </table></div>
</section>

<!-- Cadastro de garçons -->
<div class="fh-overlay" id="modalGarcons" role="dialog" aria-modal="true" aria-labelledby="tituloGarcons">
    <div class="fh-modal">
        <h3 id="tituloGarcons">Garçons</h3>
        <ul id="listaGarcons" style="list-style:none;padding:0;margin:0 0 .75rem"></ul>
        <form id="formGarcom" class="fh-toolbar">
            <div class="fh-form-group"><label for="gNome">Novo garçom</label><input class="fh-input" id="gNome" maxlength="80" required></div>
            <button type="submit" class="btn-fh">Adicionar</button>
        </form>
        <div class="fh-modal-actions"><button type="button" class="btn-fh-outline" data-fechar-modal="modalGarcons">Fechar</button></div>
    </div>
</div>
<?php
$layoutScripts = ['assets/js/gorjetas.js'];
require __DIR__ . '/_layout_fim.php';
