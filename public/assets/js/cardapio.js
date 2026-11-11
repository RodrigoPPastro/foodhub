/* Cardápio (RF01) + carrinho do cliente (RF19). O carrinho guarda só {id_produto: quantidade}. */
(function () {
    'use strict';
    var cliente = FH.el('menuLayout').dataset.cliente === '1';
    var grupos = [], filtro = 0, carrinho = {}, produtos = {};

    function cartao(p) {
        var foto = p.foto ? '<img src="' + FH.esc(p.foto) + '" alt="' + FH.esc(p.nome) + '" loading="lazy">'
            : '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 3h16v18l-3-2-2.5 2-2.5-2-2.5 2L7 19l-3 2z"/><path d="M8 8h8M8 12h8"/></svg>';
        return '<article class="menu-card"><div class="foto">' + foto + '</div><div class="corpo"><h3>' + FH.esc(p.nome) + '</h3>' +
            '<p class="desc">' + FH.esc(p.descricao || '') + '</p><div class="rodape"><span class="preco">' + FH.moeda(p.preco_venda) + '</span>' +
            (cliente ? '<button class="btn-fh btn-sm" data-add="' + p.id_produto + '">Adicionar</button>' : '') + '</div></div></article>';
    }
    function desenhar() {
        FH.el('menuCats').innerHTML = '<button class="chip ' + (!filtro ? 'ativa' : '') + '" data-cat="0">Todos</button>' +
            grupos.map(function (g) { return '<button class="chip ' + (filtro === g.id_categoria ? 'ativa' : '') + '" data-cat="' + g.id_categoria + '">' + FH.esc(g.categoria) + '</button>'; }).join('');
        var vis = grupos.filter(function (g) { return !filtro || g.id_categoria === filtro; });
        FH.el('menuLista').innerHTML = vis.length ? vis.map(function (g) {
            return '<section class="menu-secao"><h2>' + FH.esc(g.categoria) + '</h2>' + (g.descricao ? '<p>' + FH.esc(g.descricao) + '</p>' : '') +
                '<div class="menu-grid">' + g.produtos.map(cartao).join('') + '</div></section>';
        }).join('') : '<p class="vazio-msg">Nenhum produto disponível no momento.</p>';
    }

    function desenharCarrinho() {
        if (!cliente) { return; }
        var ids = Object.keys(carrinho), total = 0;
        FH.el('carVazio').hidden = ids.length > 0;
        FH.el('carItens').innerHTML = ids.map(function (id) {
            var p = produtos[id], q = carrinho[id]; total += p.preco_venda * q;
            return '<li><div><strong>' + FH.esc(p.nome) + '</strong><div class="qtd"><button data-menos="' + id + '" aria-label="Diminuir">−</button><span>' + q +
                '</span><button data-mais="' + id + '" aria-label="Aumentar">+</button></div></div><div>' + FH.moeda(p.preco_venda * q) + '</div></li>';
        }).join('');
        FH.el('carTotal').textContent = FH.moeda(total);
        FH.el('btnFinalizar').disabled = !ids.length;
    }
    function mudarQtd(id, d) {
        var q = (carrinho[id] || 0) + d;
        if (q <= 0) { delete carrinho[id]; } else { carrinho[id] = Math.min(99, q); }
        desenharCarrinho();
    }

    FH.el('menuCats').addEventListener('click', function (e) { var b = e.target.closest('[data-cat]'); if (b) { filtro = Number(b.dataset.cat); desenhar(); } });
    FH.el('menuLista').addEventListener('click', function (e) { var b = e.target.closest('[data-add]'); if (b) { mudarQtd(b.dataset.add, 1); FH.toast(produtos[b.dataset.add].nome + ' adicionado.', 'info'); } });
    if (cliente) {
        FH.el('carItens').addEventListener('click', function (e) {
            var b = e.target.closest('button'); if (!b) { return; }
            if (b.dataset.mais) { mudarQtd(b.dataset.mais, 1); } if (b.dataset.menos) { mudarQtd(b.dataset.menos, -1); }
        });
        FH.el('btnFinalizar').addEventListener('click', async function () {
            var itens = Object.keys(carrinho).map(function (id) { return { id_produto: Number(id), quantidade: carrinho[id] }; });
            this.disabled = true;
            try {
                var r = await FH.api('api/pedidos.php', { method: 'POST', corpo: { acao: 'criar_cliente', itens: itens,
                    forma_pagamento: FH.el('carPagto').value, endereco_entrega: FH.el('carEnd').value, observacao: FH.el('carObs').value } });
                FH.toast('Pedido #' + r.id_pedido + ' enviado! Acompanhe em "Meus pedidos".', 'ok');
                carrinho = {}; desenharCarrinho();
                setTimeout(function () { window.location.href = 'meus_pedidos.php'; }, 1500);
            } catch (e) { FH.toast(e.message, 'erro'); this.disabled = false; }
        });
    }

    (async function () {
        try {
            grupos = await FH.api('api/produtos.php?visao=cardapio');
            grupos.forEach(function (g) { g.produtos.forEach(function (p) { produtos[p.id_produto] = p; }); });
            desenhar(); desenharCarrinho();
        } catch (e) { FH.el('menuLista').innerHTML = '<p class="vazio-msg">' + FH.esc(e.message) + '</p>'; }
    })();
})();
