<?php
/** RF17–RF22 — quadro kanban de pedidos (Master): lançar pedido, mover status, entregador e pagamento. */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requirePermissao('pedidos');
$layoutTitulo = 'Pedidos';
$layoutAtivo  = 'pedidos';
require __DIR__ . '/_layout.php';
?>
<div class="page-heading">
    <div><h1>Pedidos</h1><p>Acompanhe do pedido à entrega. Atualiza sozinho a cada 15 segundos <span class="muted" id="pedAtualizado"></span></p></div>
    <div class="acoes">
        <select class="fh-select" id="filtroCanal" aria-label="Filtrar por canal" style="width:auto"><option value="">Todos os canais</option></select>
        <button type="button" class="btn-fh-outline" id="btnCompacto" aria-pressed="false" title="Mostra cada pedido em poucas linhas">☰ Compacto</button>
        <a href="historico.php" class="btn-fh-outline" style="text-decoration:none" title="Pedidos entregues e cancelados">🕘 Histórico</a>
        <button type="button" class="btn-fh" id="btnNovoPedido">+ Novo pedido</button>
    </div>
</div>

<div class="kanban" id="kanban"><p class="vazio-msg">Carregando…</p></div>

<!-- Novo pedido: à esquerda o cardápio com fotos por categoria (clicou na foto, entrou no pedido); à direita o pedido -->
<div class="fh-overlay" id="modalPedido" role="dialog" aria-modal="true" aria-labelledby="tituloPedido">
    <form class="fh-modal extra" id="formPedido" novalidate>
        <div class="np-layout">
            <section class="np-cardapio" aria-label="Cardápio">
                <h3 id="tituloPedido">Novo pedido</h3>
                <div class="np-busca"><input type="search" class="fh-input" id="pedBusca" placeholder="Buscar produto..." aria-label="Buscar produto"></div>
                <div class="np-chips" id="pedCats" role="tablist" aria-label="Categorias"></div>
                <div class="np-grade" id="pedGrade"></div>
            </section>
            <aside class="np-pedido" aria-label="Pedido">
                <div class="fh-form-row">
                    <div class="fh-form-group"><label for="pedCanal">Canal *</label>
                        <select class="fh-select" id="pedCanal"><option value="balcao">Balcão</option><option value="mesa">Mesa</option><option value="delivery_proprio">Delivery próprio</option></select></div>
                    <div class="fh-form-group" id="grpMesa" hidden><label for="pedMesa">Mesa *</label><select class="fh-select" id="pedMesa"></select></div>
                </div>
                <div class="fh-form-group" id="grpMesaNome" hidden><label for="pedMesaNome">Nome do cliente <span class="muted">(opcional)</span></label>
                    <input class="fh-input" id="pedMesaNome" maxlength="80" list="nomesDaMesa" placeholder="Ex.: Rodrigo" autocomplete="off">
                    <datalist id="nomesDaMesa"></datalist>
                </div>
                <div id="grpEntrega" hidden>
                    <div class="fh-form-group"><label for="pedNome">Cliente</label><input class="fh-input" id="pedNome" maxlength="100"></div>
                    <div class="fh-form-group"><label for="pedTel">Telefone (WhatsApp)</label><input class="fh-input" id="pedTel" placeholder="(+55)21-999999999"></div>
                    <div class="fh-form-group"><label for="pedEnd">Endereço de entrega *</label><input class="fh-input" id="pedEnd" maxlength="255"></div>
                </div>
                <p class="fh-label">Itens do pedido</p>
                <div class="np-itens" id="pedItens" aria-live="polite"></div>
                <div class="fh-form-group" style="margin-top:.6rem"><label for="pedObs">Observação</label><input class="fh-input" id="pedObs" maxlength="255"></div>
                <label class="fh-check"><input type="checkbox" id="pedPagarAgora"> Registrar pagamento agora</label>
                <div id="boxPagto" hidden style="margin-top:.6rem">
                    <div id="pedPagamentos"></div>
                    <button type="button" class="btn-fh-outline btn-sm" id="pedAddPagto">+ Dividir em mais uma forma</button>
                </div>
                <div class="resumo-valores"><div class="forte"><span>Total</span><strong id="pedTotal">R$ 0,00</strong></div></div>
                <div class="fh-modal-actions">
                    <button type="button" class="btn-fh-outline" data-fechar-modal="modalPedido">Cancelar</button>
                    <button type="submit" class="btn-fh">Criar pedido</button>
                </div>
            </aside>
        </div>
    </form>
</div>

<!-- Escolher entregador (ao enviar para entrega) -->
<div class="fh-overlay" id="modalEntregador" role="dialog" aria-modal="true" aria-labelledby="tituloEnt">
    <form class="fh-modal" id="formEnt">
        <h3 id="tituloEnt">Escolher entregador</h3>
        <div class="fh-form-group"><label for="selEntregador">Entregador *</label><select class="fh-select" id="selEntregador"></select></div>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalEntregador">Cancelar</button>
            <button type="submit" class="btn-fh">Confirmar</button>
        </div>
    </form>
</div>

<!-- Registrar pagamento de um pedido -->
<div class="fh-overlay" id="modalPagto" role="dialog" aria-modal="true" aria-labelledby="tituloPagto">
    <div class="fh-modal">
        <h3 id="tituloPagto">Pagamento</h3>
        <div class="resumo-valores">
            <div><span>Saldo a pagar</span><strong id="pgSaldo">R$ 0,00</strong></div>
            <div><span>Informado</span><strong id="pgInformado">R$ 0,00</strong></div>
        </div>
        <div id="pgLinhas"></div>
        <button type="button" class="btn-fh-outline btn-sm" id="pgAdd">+ Dividir em mais uma forma</button>
        <div class="fh-modal-actions">
            <button type="button" class="btn-fh-outline" data-fechar-modal="modalPagto">Cancelar</button>
            <button type="button" class="btn-fh" id="pgOk">Registrar</button>
        </div>
    </div>
</div>
<?php
$layoutScripts = ['assets/js/pagamento-ui.js', 'assets/js/pedidos.js'];
require __DIR__ . '/_layout_fim.php';
