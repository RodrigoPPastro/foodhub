<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/PeriodoService.php';

AuthGuard::requireMaster();

$tituloPagina = 'Fechamento de Caixa';
$paginaAtiva  = 'fechamento_caixa.php';
$hoje = PeriodoService::hoje();
$erroPdf = SessionService::getFlash('erro_pdf'); // vem de fechamento_caixa_pdf.php

require __DIR__ . '/partials/topo_financeiro.php';
?>
    <h1 class="h3 mb-4">Fechamento de Caixa</h1>

    <form id="formData" class="row g-2 align-items-end mb-4">
        <div class="col-12 col-sm-auto">
            <label for="data" class="form-label mb-1">Data de referência</label>
            <input type="date" id="data" class="form-control" value="<?= htmlspecialchars($hoje) ?>" max="<?= htmlspecialchars($hoje) ?>" required>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-fh">Consultar</button></div>
        <div class="col-auto ms-sm-auto d-flex gap-2">
            <a id="btnPdf" class="btn btn-fh-ouro" href="#">Baixar PDF</a>
            <button type="button" id="btnFechar" class="btn btn-fh" disabled>Fechar caixa</button>
        </div>
    </form>

    <div id="areaSituacao" class="mb-3"></div>
    <div id="areaPendencias"></div>

    <section class="row g-3 mb-4" aria-label="Resumo do caixa">
        <div class="col-12 col-md-4"><div class="fh-cartao"><div class="fh-kpi-rotulo">Total geral</div><div class="fh-kpi-valor" id="resTotal">—</div></div></div>
        <div class="col-6 col-md-4"><div class="fh-cartao ouro"><div class="fh-kpi-rotulo">Pedidos</div><div class="fh-kpi-valor" id="resPedidos">—</div></div></div>
        <div class="col-6 col-md-4"><div class="fh-cartao ouro"><div class="fh-kpi-rotulo">Ticket médio</div><div class="fh-kpi-valor" id="resTicket">—</div></div></div>
    </section>

    <div class="row g-3 mb-4">
        <section class="col-12 col-lg-6" aria-label="Totais por forma de pagamento">
            <div class="fh-cartao">
                <h2 class="h6 mb-3">Por forma de pagamento</h2>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Forma</th><th class="num">Total</th><th class="num">%</th></tr></thead>
                    <tbody id="tbForma"></tbody>
                </table></div>
            </div>
        </section>
        <section class="col-12 col-lg-6" aria-label="Totais por canal">
            <div class="fh-cartao">
                <h2 class="h6 mb-3">Por canal de venda</h2>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Canal</th><th class="num">Pedidos</th><th class="num">Total</th><th class="num">%</th></tr></thead>
                    <tbody id="tbCanal"></tbody>
                </table></div>
            </div>
        </section>
    </div>

    <section class="fh-cartao" id="boxObs">
        <label for="obs" class="form-label">Observações do fechamento</label>
        <textarea id="obs" class="form-control" rows="2" maxlength="500" placeholder="Ex.: diferença de troco, sangria, ocorrências do dia…"></textarea>
    </section>

    <!-- Modal de confirmação (substitui confirm() nativo) -->
    <div class="modal fade" id="modalFechar" tabindex="-1" aria-labelledby="modalFecharTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5 fh-titulo" id="modalFecharTitulo">Fechar o caixa?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">Você vai fechar o caixa de <strong id="modalData"></strong> no valor de <strong id="modalTotal"></strong>.
                Depois de fechado, o fechamento <strong>não pode ser refeito</strong>.</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-fh" id="btnConfirmar">Confirmar fechamento</button>
            </div>
        </div></div>
    </div>

<?php require __DIR__ . '/partials/rodape_financeiro.php'; ?>
<script>window.FH_ERRO_PDF = <?= json_encode($erroPdf, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="assets/js/fechamento_caixa.js"></script>
</body>
</html>
