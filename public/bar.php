<?php
/** Painel do bar: só os itens com setor de preparo "Bar" (sucos, drinks, sobremesas), com cronômetro e alerta de atraso. */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('bar');
$layoutTitulo = 'Bar';
$layoutAtivo  = 'bar';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Bar</h1><p>Somente os itens preparados no bar, do pedido mais antigo para o mais novo. Itens sem preparo (água, lata) vão direto para a mesa. Atualiza sozinho a cada 10 segundos.</p></div>
    <div class="acoes"><span class="muted" id="cozAtualizado"></span></div>
</div>
<div class="cozinha-grid" id="gridCozinha" data-setor="bar"><p class="vazio-msg">Carregando…</p></div>
<?php
$layoutScripts = ['assets/js/cozinha.js'];
require __DIR__ . '/_layout_fim.php';
