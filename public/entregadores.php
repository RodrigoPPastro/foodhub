<?php
/** RF21 — cadastro de entregadores (Master). O status "em rota" é automático (pedido saiu para entrega). */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('entregadores');
$layoutTitulo = 'Entregadores';
$layoutAtivo  = 'entregadores';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Entregadores</h1><p>Equipe de entrega do delivery próprio. Atribua o entregador na tela de Pedidos.</p></div>
    <div class="acoes"><button type="button" class="btn-fh" id="btnNovo">+ Novo entregador</button></div>
</div>
<div class="fh-card-inner">
    <div class="table-wrap">
        <table class="fh-table">
            <thead><tr><th>Nome</th><th>Telefone</th><th>Veículo</th><th>Status</th><th class="num">Em andamento</th><th class="num">Total de pedidos</th><th></th></tr></thead>
            <tbody id="tbEntregadores"><tr><td colspan="7" class="vazio-msg">Carregando…</td></tr></tbody>
        </table>
    </div>
</div>

<div class="fh-overlay" id="modalEntregador" role="dialog" aria-modal="true" aria-labelledby="tituloEntregador">
    <form class="fh-modal" id="formEntregador" novalidate>
        <h3 id="tituloEntregador">Novo entregador</h3>
        <input type="hidden" id="entId">
        <div class="fh-form-group"><label for="entNome">Nome *</label><input class="fh-input" id="entNome" maxlength="100"></div>
        <div class="fh-form-row">
            <div class="fh-form-group"><label for="entTel">Telefone *</label><input class="fh-input" id="entTel" placeholder="(+55)21-999999999"></div>
            <div class="fh-form-group"><label for="entVeiculo">Veículo</label><input class="fh-input" id="entVeiculo" maxlength="40" placeholder="Moto, bicicleta…"></div>
        </div>
        <div class="fh-form-group"><label for="entStatus">Situação</label>
            <select class="fh-select" id="entStatus"><option value="disponivel">Disponível</option><option value="inativo">Inativo</option></select></div>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalEntregador">Cancelar</button>
            <button type="submit" class="btn-fh">Salvar</button>
        </div>
    </form>
</div>
<?php
$layoutScripts = ['assets/js/entregadores.js'];
require __DIR__ . '/_layout_fim.php';
