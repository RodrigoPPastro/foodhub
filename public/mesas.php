<?php
/** RF22 — mesas, conta e divisão de pagamento (Master). */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('mesas');
$layoutTitulo = 'Mesas';
$layoutAtivo  = 'mesas';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Mesas</h1><p>Ocupação em tempo real. Feche a conta dividindo em várias formas de pagamento.</p></div>
    <div class="acoes"><button type="button" class="btn-fh" id="btnNovaMesa">+ Nova mesa</button></div>
</div>
<div class="mesas-grid" id="gridMesas"><p class="vazio-msg">Carregando…</p></div>

<div class="fh-overlay" id="modalMesa" role="dialog" aria-modal="true" aria-labelledby="tituloMesa">
    <form class="fh-modal" id="formMesa" novalidate>
        <h3 id="tituloMesa">Nova mesa</h3>
        <input type="hidden" id="mesaId">
        <div class="fh-form-row">
            <div class="fh-form-group"><label for="mesaNumero">Número *</label><input class="fh-input" id="mesaNumero" type="number" min="1"></div>
            <div class="fh-form-group"><label for="mesaCap">Capacidade *</label><input class="fh-input" id="mesaCap" type="number" min="1" value="4"></div>
        </div>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalMesa">Cancelar</button>
            <button type="submit" class="btn-fh">Salvar</button>
        </div>
    </form>
</div>

<div class="fh-overlay" id="modalConta" role="dialog" aria-modal="true" aria-labelledby="tituloConta">
    <div class="fh-modal largo">
        <h3 id="tituloConta">Conta da mesa</h3>
        <p class="muted" id="contaInfo" style="margin:-.3rem 0 .6rem"></p>
        <div class="table-wrap"><table class="fh-table conta-extrato">
            <thead><tr><th class="num">Qtd</th><th>Produto</th><th class="num">Unitário</th><th class="num">Subtotal</th></tr></thead>
            <tbody id="contaItens"></tbody>
        </table></div>
        <label class="check-linha" style="display:flex;gap:.5rem;align-items:center;margin:.4rem 0">
            <input type="checkbox" id="contaTaxa"> Incluir taxa de serviço de 10% (garçom)
        </label>
        <div class="fh-form-group" id="grupoGarcom" hidden>
            <label for="contaGarcom">Garçom que atendeu (recebe os 10%)</label>
            <select class="fh-select" id="contaGarcom"></select>
        </div>
        <div class="resumo-valores">
            <div><span>Total consumido</span><strong id="contaConsumido">R$ 0,00</strong></div>
            <div id="linhaJaPago" hidden><span>Já pago</span><strong id="contaJaPago">R$ 0,00</strong></div>
            <div><span>Total em aberto</span><strong id="contaEmAberto">R$ 0,00</strong></div>
            <div id="linhaTaxa" hidden><span>Taxa de serviço (10%)</span><strong id="contaTaxaValor">R$ 0,00</strong></div>
            <div id="linhaTotalPagar" hidden><span>Total a pagar</span><strong id="contaTotal">R$ 0,00</strong></div>
            <div><span>Informado</span><strong id="contaInformado">R$ 0,00</strong></div>
            <div class="forte"><span>Falta</span><strong id="contaFalta">R$ 0,00</strong></div>
        </div>
        <div id="contaPagamentos"></div>
        <button type="button" class="btn-fh-outline btn-sm" id="btnAddPagto">+ Dividir em mais uma forma</button>
        <p class="fh-hint">A soma das formas de pagamento deve ser igual ao total (com a taxa de 10%, o pagamento é integral). O valor é distribuído pelos pedidos mais antigos primeiro.</p>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalConta">Cancelar</button>
            <button type="button" class="btn-fh-outline" id="btnImprimirConta">🖨 Imprimir conferência</button>
            <button type="button" class="btn-fh" id="btnFecharConta">Fechar conta</button>
        </div>
    </div>
</div>
<?php
$layoutScripts = ['assets/js/pagamento-ui.js', 'assets/js/mesas.js'];
require __DIR__ . '/_layout_fim.php';
