<?php
/** RF19 — o cliente acompanha o status dos seus pedidos de delivery. */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requireCliente();
$layoutTitulo = 'Meus pedidos';
$layoutAtivo  = 'meus_pedidos';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading"><div><h1>Meus pedidos</h1><p>Acompanhe o andamento. A página atualiza sozinha.</p></div>
    <div class="acoes"><a class="btn-fh" href="cardapio.php">Fazer novo pedido</a></div></div>
<div id="listaMeus"><p class="vazio-msg">Carregando…</p></div>
<?php
$layoutScripts = ['assets/js/meus_pedidos.js'];
require __DIR__ . '/_layout_fim.php';
