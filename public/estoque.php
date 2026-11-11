<?php
/** RF15/RF16 — insumos, movimentações de estoque e alerta de nível mínimo (Master). */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('estoque');
$layoutTitulo = 'Estoque';
$layoutAtivo  = 'estoque';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Estoque</h1><p>Insumos usados nas fichas técnicas. A baixa é automática quando o pedido entra em preparo.</p></div>
    <div class="acoes"><button type="button" class="btn-fh" id="btnNovoInsumo">+ Novo insumo</button></div>
</div>
<div id="alertaEstoque"></div>

<div class="fh-tabs" id="abasEst" role="tablist">
    <button class="fh-tab ativa" data-aba="insumos" role="tab" aria-selected="true">Insumos</button>
    <button class="fh-tab" data-aba="mov" role="tab" aria-selected="false">Movimentações</button>
</div>

<div data-painel="insumos" class="fh-tab-painel">
    <div class="fh-card-inner">
        <div class="fh-toolbar mb-2">
            <div class="fh-form-group"><label for="eBusca">Buscar insumo</label><input class="fh-input" id="eBusca" placeholder="Nome…"></div>
            <label class="fh-check"><input type="checkbox" id="eBaixo"> Só abaixo do mínimo</label>
        </div>
        <div class="table-wrap"><table class="fh-table">
            <thead><tr><th>Insumo</th><th class="num">Estoque</th><th class="num">Mínimo</th><th>Nível</th><th class="num">Custo unit.</th><th class="num">Valor em estoque</th><th></th></tr></thead>
            <tbody id="tbInsumos"><tr><td colspan="7" class="vazio-msg">Carregando…</td></tr></tbody>
        </table></div>
    </div>
</div>
<div data-painel="mov" class="fh-tab-painel" hidden>
    <div class="fh-card-inner">
        <div class="table-wrap"><table class="fh-table">
            <thead><tr><th>Quando</th><th>Insumo</th><th>Tipo</th><th class="num">Quantidade</th><th>Motivo</th><th>Usuário</th></tr></thead>
            <tbody id="tbMov"><tr><td colspan="6" class="vazio-msg">Carregando…</td></tr></tbody>
        </table></div>
    </div>
</div>

<div class="fh-overlay" id="modalInsumo" role="dialog" aria-modal="true" aria-labelledby="tituloInsumo">
    <form class="fh-modal" id="formInsumo" novalidate>
        <h3 id="tituloInsumo">Novo insumo</h3>
        <input type="hidden" id="insId">
        <div class="fh-form-group"><label for="insNome">Nome *</label><input class="fh-input" id="insNome" maxlength="100"></div>
        <div class="fh-form-row">
            <div class="fh-form-group"><label for="insUn">Unidade *</label><select class="fh-select" id="insUn"></select></div>
            <div class="fh-form-group"><label for="insMin">Estoque mínimo *</label><input class="fh-input" id="insMin" inputmode="decimal" value="0"></div>
            <div class="fh-form-group"><label for="insCusto">Custo unitário (R$) *</label><input class="fh-input" id="insCusto" inputmode="decimal" value="0"></div>
        </div>
        <div class="fh-form-group" id="grpInicial"><label for="insInicial">Estoque inicial</label><input class="fh-input" id="insInicial" inputmode="decimal" value="0"></div>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalInsumo">Cancelar</button>
            <button type="submit" class="btn-fh">Salvar</button>
        </div>
    </form>
</div>

<div class="fh-overlay" id="modalMov" role="dialog" aria-modal="true" aria-labelledby="tituloMov">
    <form class="fh-modal" id="formMov" novalidate>
        <h3 id="tituloMov">Movimentar estoque</h3>
        <input type="hidden" id="movId">
        <div class="fh-form-row">
            <div class="fh-form-group"><label for="movTipo">Tipo *</label>
                <select class="fh-select" id="movTipo"><option value="entrada">Entrada (compra)</option><option value="saida">Saída</option><option value="perda">Perda / desperdício</option></select></div>
            <div class="fh-form-group"><label for="movQtd">Quantidade *</label><input class="fh-input" id="movQtd" inputmode="decimal"></div>
        </div>
        <div class="fh-form-group"><label for="movMotivo">Motivo</label><input class="fh-input" id="movMotivo" maxlength="255" placeholder="Ex.: compra no fornecedor X"></div>
        <div id="boxCompra">
            <label class="fh-check"><input type="checkbox" id="movGerarConta"> Gerar conta a pagar desta compra</label>
            <div id="boxConta" hidden style="margin-top:.8rem">
                <div class="fh-form-group"><label for="movFornecedor">Fornecedor</label><input class="fh-input" id="movFornecedor" maxlength="100"></div>
                <div class="fh-form-row">
                    <div class="fh-form-group"><label for="movValor">Valor total (R$) *</label><input class="fh-input" id="movValor" inputmode="decimal"></div>
                    <div class="fh-form-group"><label for="movVenc">Vencimento *</label><input class="fh-input" id="movVenc" type="date"></div>
                </div>
            </div>
        </div>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalMov">Cancelar</button>
            <button type="submit" class="btn-fh">Registrar</button>
        </div>
    </form>
</div>
<?php
$layoutScripts = ['assets/js/estoque.js'];
require __DIR__ . '/_layout_fim.php';
