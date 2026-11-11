<?php
/** RF13 — categorias do cardápio (Master). A página só monta o esqueleto; os dados vêm de api/categorias.php. */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('categorias');

$layoutTitulo = 'Categorias';
$layoutAtivo  = 'categorias';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Categorias</h1><p>Agrupam os produtos do cardápio (ex.: Lanches, Bebidas, Sobremesas).</p></div>
    <div class="acoes"><button type="button" class="btn-fh" id="btnNova">+ Nova categoria</button></div>
</div>

<div class="fh-card-inner">
    <div class="table-wrap">
        <table class="fh-table">
            <thead><tr><th>Nome</th><th>Descrição</th><th class="num">Produtos</th><th></th></tr></thead>
            <tbody id="tbCategorias"><tr><td colspan="4" class="vazio-msg">Carregando…</td></tr></tbody>
        </table>
    </div>
</div>

<div class="fh-overlay" id="modalCategoria" role="dialog" aria-modal="true" aria-labelledby="tituloCategoria">
    <form class="fh-modal" id="formCategoria" novalidate>
        <h3 id="tituloCategoria">Nova categoria</h3>
        <input type="hidden" id="catId">
        <div class="fh-form-group"><label for="catNome">Nome *</label><input class="fh-input" id="catNome" maxlength="60" required></div>
        <div class="fh-form-group"><label for="catDesc">Descrição</label><textarea class="fh-textarea" id="catDesc" maxlength="255"></textarea></div>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalCategoria">Cancelar</button>
            <button type="submit" class="btn-fh">Salvar</button>
        </div>
    </form>
</div>
<?php
$layoutScripts = ['assets/js/categorias.js'];
require __DIR__ . '/_layout_fim.php';
