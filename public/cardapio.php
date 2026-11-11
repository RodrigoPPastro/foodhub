<?php
/**
 * RF01 — cardápio. Master: visualização. Cliente comum: monta o carrinho e faz o pedido pelo delivery próprio (RF19).
 * Os preços exibidos vêm do banco; ao finalizar, o servidor recalcula tudo (nunca confia no navegador).
 */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
require_once __DIR__ . '/../src/Services/SessionService.php';
AuthGuard::requireLogin();
$ehCliente = SessionService::perfilAtual() === 'comum';   // master/funcionário só visualizam
$layoutTitulo = 'Cardápio';
$layoutAtivo  = 'cardapio';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Cardápio</h1><p><?= $ehCliente ? 'Escolha seus pratos e finalize o pedido para entrega.' : 'Produtos ativos como o cliente os vê. Edite em Produtos.' ?></p></div>
</div>

<div class="menu-layout <?= $ehCliente ? '' : 'sem-carrinho' ?>" id="menuLayout" data-cliente="<?= $ehCliente ? '1' : '0' ?>">
    <div>
        <div class="menu-cats" id="menuCats"></div>
        <div id="menuLista"><p class="vazio-msg">Carregando cardápio…</p></div>
    </div>
    <?php if ($ehCliente): ?>
    <aside class="fh-card-inner carrinho" aria-label="Seu pedido">
        <h2>Seu pedido</h2>
        <ul class="carrinho-itens" id="carItens"></ul>
        <p class="carrinho-vazio" id="carVazio">Seu carrinho está vazio.</p>
        <div class="carrinho-total"><span>Total</span><strong id="carTotal">R$ 0,00</strong></div>
        <div class="fh-form-group"><label for="carPagto">Forma de pagamento</label>
            <select class="fh-select" id="carPagto">
                <option value="pix">Pix</option><option value="credito">Cartão de crédito</option>
                <option value="debito">Cartão de débito</option><option value="dinheiro">Dinheiro</option><option value="vale">Vale-refeição</option>
            </select></div>
        <div class="fh-form-group"><label for="carEnd">Endereço de entrega</label>
            <input class="fh-input" id="carEnd" maxlength="255" placeholder="Em branco = endereço do seu cadastro"></div>
        <div class="fh-form-group"><label for="carObs">Observação</label><input class="fh-input" id="carObs" maxlength="255"></div>
        <button type="button" class="btn-fh" id="btnFinalizar" style="width:100%" disabled>Finalizar pedido</button>
    </aside>
    <?php endif; ?>
</div>
<?php
$layoutScripts = ['assets/js/cardapio.js'];
require __DIR__ . '/_layout_fim.php';
