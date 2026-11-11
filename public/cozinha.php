<?php
/** Painel da cozinha (RF17/RF18): só os itens com setor de preparo "Cozinha", com cronômetro e alerta de atraso. */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('cozinha');
$layoutTitulo = 'Cozinha';
$layoutAtivo  = 'cozinha';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Cozinha</h1><p>Somente os itens preparados na cozinha, do pedido mais antigo para o mais novo. Bebidas do bar e itens sem preparo não aparecem aqui. Atualiza sozinho a cada 10 segundos.</p></div>
    <div class="acoes"><span class="muted" id="cozAtualizado"></span></div>
</div>
<div class="cozinha-grid" id="gridCozinha" data-setor="cozinha"><p class="vazio-msg">Carregando…</p></div>
<?php
$layoutScripts = ['assets/js/cozinha.js'];
require __DIR__ . '/_layout_fim.php';
