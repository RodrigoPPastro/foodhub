<?php
/** RF23 — fechamento de caixa (Master): prévia ao vivo, fechamento definitivo e PDF. Dados via api/fechamento_caixa.php. */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/PeriodoService.php';

AuthGuard::requirePermissao('caixa');

$hoje = PeriodoService::hoje();
$erroPdf = SessionService::getFlash('erro_pdf'); // vem de fechamento_caixa_pdf.php

$layoutTitulo = 'Fechamento de Caixa';
$layoutAtivo  = 'caixa';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading"><div><h1>Fechamento de caixa</h1><p>Confira os recebimentos do dia e feche o caixa. Depois de fechado, não pode ser refeito.</p></div></div>

<div class="fh-card-inner">
    <form id="formData" class="fh-toolbar">
        <div class="fh-form-group" style="max-width:220px"><label for="data">Data de referência</label>
            <input type="date" id="data" class="fh-input" value="<?= h($hoje) ?>" max="<?= h($hoje) ?>" required></div>
        <button type="submit" class="btn-fh-outline">Consultar</button>
        <span style="flex:1"></span>
        <a id="btnPdf" class="btn-fh-gold" href="#">Baixar PDF</a>
        <button type="button" id="btnFechar" class="btn-fh" disabled>Fechar caixa</button>
    </form>
</div>

<div id="areaSituacao" class="mb-2"></div>
<div id="areaPendencias"></div>

<section class="fh-grid c3 mb-3" aria-label="Resumo do caixa">
    <div class="metric-card"><span>Total geral</span><strong id="resTotal">—</strong></div>
    <div class="metric-card gold"><span>Pedidos</span><strong id="resPedidos">—</strong></div>
    <div class="metric-card gold"><span>Ticket médio</span><strong id="resTicket">—</strong></div>
</section>

<div class="fh-grid c2">
    <section class="fh-card-inner" aria-label="Totais por forma de pagamento"><h2>Por forma de pagamento</h2>
        <div class="table-wrap"><table class="fh-table">
            <thead><tr><th>Forma</th><th class="num">Total</th><th class="num">%</th></tr></thead><tbody id="tbForma"></tbody></table></div></section>
    <section class="fh-card-inner" aria-label="Totais por canal"><h2>Por canal de venda</h2>
        <div class="table-wrap"><table class="fh-table">
            <thead><tr><th>Canal</th><th class="num">Pedidos</th><th class="num">Total</th><th class="num">%</th></tr></thead><tbody id="tbCanal"></tbody></table></div></section>
</div>

<section class="fh-card-inner" id="boxObs">
    <div class="fh-form-group" style="margin:0"><label for="obs">Observações do fechamento</label>
        <textarea id="obs" class="fh-textarea" maxlength="500" placeholder="Ex.: diferença de troco, sangria, ocorrências do dia…"></textarea></div>
</section>

<div class="fh-overlay" id="modalFechar" role="dialog" aria-modal="true" aria-labelledby="modalFecharTitulo">
    <div class="fh-modal">
        <h3 id="modalFecharTitulo">Fechar o caixa?</h3>
        <p>Você vai fechar o caixa de <strong id="modalData"></strong> no valor de <strong id="modalTotal"></strong>. Depois de fechado, o fechamento <strong>não pode ser refeito</strong>.</p>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalFechar">Cancelar</button>
            <button type="button" class="btn-fh" id="btnConfirmar">Confirmar fechamento</button>
        </div>
    </div>
</div>
<?php
$layoutHead = '';
echo '<script>window.FH_ERRO_PDF = ' . json_encode($erroPdf, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
$layoutScripts = ['assets/js/fechamento_caixa.js'];
require __DIR__ . '/_layout_fim.php';
