<?php
/** RF13/RF14 — produtos do cardápio, foto e ficha técnica com custo e margem (Master). */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('produtos');
$layoutTitulo = 'Produtos';
$layoutAtivo  = 'produtos';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Produtos</h1><p>Cadastre pratos e bebidas. A ficha técnica calcula custo e margem automaticamente.</p></div>
    <div class="acoes"><button type="button" class="btn-fh" id="btnNovoProd">+ Novo produto</button></div>
</div>
<div class="fh-card-inner">
    <div class="fh-toolbar mb-2">
        <div class="fh-form-group"><label for="fBusca">Buscar</label><input class="fh-input" id="fBusca" placeholder="Nome do produto…"></div>
        <div class="fh-form-group"><label for="fCat">Categoria</label><select class="fh-select" id="fCat"><option value="">Todas</option></select></div>
        <div class="fh-form-group"><label for="fAtivo">Situação</label><select class="fh-select" id="fAtivo"><option value="">Todos</option><option value="1">Ativos</option><option value="0">Inativos</option></select></div>
    </div>
    <div class="table-wrap"><table class="fh-table">
        <thead><tr><th></th><th>Produto</th><th>Categoria</th><th>Setor</th><th class="num">Preço</th><th class="num">Custo</th><th class="num">Margem</th><th>Situação</th><th></th></tr></thead>
        <tbody id="tbProdutos"><tr><td colspan="9" class="vazio-msg">Carregando…</td></tr></tbody>
    </table></div>
</div>

<div class="fh-overlay" id="modalProduto" role="dialog" aria-modal="true" aria-labelledby="tituloProduto">
    <form class="fh-modal largo" id="formProduto" novalidate>
        <h3 id="tituloProduto">Novo produto</h3>
        <input type="hidden" id="prodId">
        <div class="fh-form-row">
            <div class="fh-form-group"><label for="prodNome">Nome *</label><input class="fh-input" id="prodNome" maxlength="100"></div>
            <div class="fh-form-group"><label for="prodCat">Categoria *</label><select class="fh-select" id="prodCat"></select></div>
            <div class="fh-form-group"><label for="prodPreco">Preço de venda (R$) *</label><input class="fh-input" id="prodPreco" inputmode="decimal" placeholder="0,00"></div>
        </div>
        <div class="fh-form-group"><label for="prodSetor">Setor de preparo *</label>
            <select class="fh-select" id="prodSetor">
                <option value="cozinha">Cozinha</option>
                <option value="bar">Bar</option>
                <option value="nenhum">Sem preparo (vai direto para a mesa)</option>
            </select>
            <p class="fh-hint">Define em qual tela o item aparece. "Sem preparo" é para itens prontos, como água e refrigerante em lata.</p></div>
        <div class="fh-form-group"><label for="prodDesc">Descrição</label><textarea class="fh-textarea" id="prodDesc" maxlength="255"></textarea></div>
        <label class="fh-check mb-2"><input type="checkbox" id="prodAtivo" checked> Ativo no cardápio</label>

        <div id="blocoExtra" hidden>
            <div class="fh-tabs" id="abasProd" role="tablist">
                <button type="button" class="fh-tab ativa" data-aba="foto" role="tab" aria-selected="true">Foto</button>
                <button type="button" class="fh-tab" data-aba="ficha" role="tab" aria-selected="false">Ficha técnica</button>
            </div>
            <div data-painel="foto" class="fh-tab-painel">
                <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
                    <img id="prodFotoImg" class="fh-thumb" style="width:90px;height:90px" alt="Foto do produto" hidden>
                    <div><input type="file" id="prodFoto" accept="image/jpeg,image/png,image/webp">
                        <p class="fh-hint">JPG, PNG ou WebP até 2 MB.</p>
                        <button type="button" class="btn-fh-outline btn-sm" id="btnEnviarFoto">Enviar foto</button>
                        <button type="button" class="btn-fh-ghost btn-sm" id="btnRemoverFoto">Remover foto</button></div>
                </div>
            </div>
            <div data-painel="ficha" class="fh-tab-painel" hidden>
                <div class="table-wrap"><table class="fh-table">
                    <thead><tr><th>Insumo</th><th class="num">Quantidade</th><th>Un.</th><th class="num">Custo</th><th></th></tr></thead>
                    <tbody id="tbFicha"></tbody></table></div>
                <div class="fh-toolbar" style="margin-top:.7rem">
                    <div class="fh-form-group"><label for="fichaInsumo">Adicionar insumo</label><select class="fh-select" id="fichaInsumo"></select></div>
                    <button type="button" class="btn-fh-outline btn-sm" id="btnAddInsumo">Adicionar</button>
                </div>
                <div class="resumo-valores" id="fichaResumo"></div>
                <button type="button" class="btn-fh btn-sm" id="btnSalvarFicha">Salvar ficha técnica</button>
            </div>
        </div>
        <p class="fh-hint" id="dicaNovo">Salve o produto para liberar foto e ficha técnica.</p>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalProduto">Fechar</button>
            <button type="submit" class="btn-fh">Salvar produto</button>
        </div>
    </form>
</div>
<?php
$layoutScripts = ['assets/js/produtos.js'];
require __DIR__ . '/_layout_fim.php';
