/* Acompanhamento do cliente (RF19): linha do tempo simples de status. */
(function () {
    'use strict';
    var ETAPAS = ['novo', 'em preparo', 'pronto', 'saiu', 'entregue'];
    var box = FH.el('listaMeus');
    function tempo(p) {
        var i = ETAPAS.indexOf(p.status);
        return '<div class="status-barras">' + ETAPAS.map(function (e, k) {
            return '<div class="status-barra"><span>' + FH.esc(FH.rotuloStatus(e)) + '</span><span class="trilho"><i style="width:' + (p.status !== 'cancelado' && k <= i ? 100 : 0) + '%"></i></span><span></span></div>';
        }).join('') + '</div>';
    }
    async function carregar() {
        try {
            var l = await FH.api('api/pedidos.php?visao=meus');
            box.innerHTML = l.length ? l.map(function (p) {
                return '<div class="fh-card-inner"><h2>Pedido #' + p.id_pedido + ' <small>' + FH.esc(p.data_hora) + ' · <span class="fh-badge ' + FH.classeStatus(p.status) + '">' + FH.esc(p.status_rotulo) + '</span></small></h2>' +
                    '<ul>' + p.itens.map(function (i) { return '<li>' + i.quantidade + '× ' + FH.esc(i.nome) + '</li>'; }).join('') + '</ul>' +
                    '<p><strong>Total: ' + FH.moeda(p.valor_total) + '</strong>' + (p.entregador ? ' · Entregador: ' + FH.esc(p.entregador) : '') + '</p>' + tempo(p) + '</div>';
            }).join('') : '<p class="vazio-msg">Você ainda não fez nenhum pedido. <a href="cardapio.php">Ver o cardápio</a></p>';
        } catch (e) { box.innerHTML = '<p class="vazio-msg">' + FH.esc(e.message) + '</p>'; }
    }
    carregar(); setInterval(carregar, 15000);
})();
