/* Simulador do webhook (RF20) e histórico de notificações (RF23).
 * O token é lido do atributo data- da página para simular exatamente o que a plataforma faria. */
(function () {
    'use strict';
    FH.abas(FH.el('abas').parentNode);
    var ultimo = null, produtos = [], carrinho = [];   // carrinho: [{id_produto, quantidade}]
    var NOMES = ['Ana Souza', 'Bruno Lima', 'Carla Mendes', 'Diego Rocha', 'Elisa Prado', 'Felipe Nunes', 'Gabi Torres', 'Hugo Alves'];
    var RUAS = ['Rua das Flores, 100, Centro', 'Av. Brasil, 2500, Bonsucesso', 'Rua Voluntários da Pátria, 45, Botafogo', 'Rua Conde de Bonfim, 800, Tijuca'];
    var SETOR = { cozinha: 'Cozinha', bar: 'Bar', nenhum: 'Sem preparo' };

    async function iniciar() {
        try {
            var q = await FH.api('api/pedidos.php?visao=quadro');
            var proprios = ['balcao', 'mesa', 'delivery_proprio'];
            FH.el('simPlat').innerHTML = Object.keys(q.canais).filter(function (c) { return proprios.indexOf(c) < 0; })
                .map(function (c) { return '<option value="' + FH.esc(c) + '">' + FH.esc(q.canais[c]) + '</option>'; }).join('');
            var cardapio = await FH.api('api/produtos.php?visao=cardapio');
            produtos = [];
            cardapio.forEach(function (g) { g.produtos.forEach(function (p) { produtos.push(p); }); });
            FH.el('simProd').innerHTML = produtos.map(function (p) { return '<option value="' + p.id_produto + '">' + FH.esc(p.nome) + ' — ' + FH.moeda(p.preco_venda) + '</option>'; }).join('');
            desenharCarrinho();
        } catch (e) { FH.toast(e.message, 'erro'); }
        carregarMsgs();
    }

    async function enviar(payload) {
        // Chamada igual à da plataforma: token no cabeçalho, corpo JSON.
        var r = await fetch('api/pedidos_webhook.php', { method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Webhook-Token': window.FH_WEBHOOK_TOKEN || '' }, body: JSON.stringify(payload) });
        var j = await r.json().catch(function () { return {}; });
        FH.el('simResp').textContent = 'HTTP ' + r.status + '\n' + JSON.stringify(j, null, 2);
        if (j.sucesso) { FH.toast(j.dados && j.dados.duplicado ? 'Pedido já recebido antes (não duplicou).' : 'Pedido recebido da plataforma.', 'ok'); carregarMsgs(); }
        else { FH.toast(j.mensagem || 'Falha no webhook.', 'erro'); }
    }
    /* ── Carrinho do pedido simulado ─────────────────────────────── */
    function prod(id) { return produtos.find(function (p) { return p.id_produto === id; }); }
    function desenharCarrinho() {
        var total = 0;
        FH.el('simItens').innerHTML = carrinho.length ? carrinho.map(function (c, k) {
            var p = prod(c.id_produto); if (!p) { return ''; }
            var sub = p.preco_venda * c.quantidade; total += sub;
            return '<tr><td class="num">' + c.quantidade + '</td><td>' + FH.esc(p.nome) + '</td><td><span class="setor-tag s-' + FH.esc(p.setor_preparo || 'cozinha') + '">' +
                FH.esc(SETOR[p.setor_preparo] || 'Cozinha') + '</span></td><td class="num">' + FH.moeda(sub) + '</td>' +
                '<td><button type="button" class="btn-fh-ghost btn-sm" data-rm="' + k + '" aria-label="Remover">×</button></td></tr>';
        }).join('') : '<tr><td colspan="5" class="vazio-msg">Adicione os produtos do pedido.</td></tr>';
        FH.el('simTotal').textContent = FH.moeda(Math.round(total * 100) / 100);
    }
    function adicionar(id, qtd) {
        var item = carrinho.find(function (c) { return c.id_produto === id; });
        if (item) { item.quantidade = Math.min(99, item.quantidade + qtd); } else { carrinho.push({ id_produto: id, quantidade: qtd }); }
        desenharCarrinho();
    }
    FH.el('simAdd').addEventListener('click', function () {
        var q = Math.max(1, Math.min(99, Number(FH.el('simQtd').value) || 1));
        adicionar(Number(FH.el('simProd').value), q); FH.el('simQtd').value = 1;
    });
    FH.el('simItens').addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-rm]'); if (b) { carrinho.splice(Number(b.dataset.rm), 1); desenharCarrinho(); }
    });
    function sortear(lista) { return lista[Math.floor(Math.random() * lista.length)]; }
    FH.el('btnAleatorio').addEventListener('click', function () {
        if (!produtos.length) { return; }
        // Sorteia um lanche + algo do bar + uma bebida pronta (quando existirem), para mostrar a divisão por setor
        carrinho = [];
        ['cozinha', 'bar', 'nenhum'].forEach(function (s) {
            var doSetor = produtos.filter(function (p) { return (p.setor_preparo || 'cozinha') === s; });
            if (doSetor.length && (s === 'cozinha' || Math.random() < 0.75)) { adicionar(sortear(doSetor).id_produto, 1 + Math.floor(Math.random() * 2)); }
        });
        if (!carrinho.length) { adicionar(sortear(produtos).id_produto, 1); }
        var opcoes = FH.el('simPlat').options; if (opcoes.length) { FH.el('simPlat').selectedIndex = Math.floor(Math.random() * opcoes.length); }
        FH.el('simNome').value = sortear(NOMES); FH.el('simEnd').value = sortear(RUAS);
        FH.el('simPagto').value = sortear(['pix', 'credito', 'credito', 'debito', 'vale']);
        FH.toast('Pedido sorteado. Confira e clique em "Enviar pedido simulado".', 'ok');
    });

    FH.el('formSim').addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (!carrinho.length) { FH.toast('Adicione pelo menos um produto ao pedido.', 'erro'); return; }
        var sigla = FH.el('simPlat').value === 'ifood' ? 'IF' : FH.el('simPlat').value.slice(0, 2).toUpperCase();
        ultimo = {
            plataforma: FH.el('simPlat').value, id_pedido_externo: sigla + '-' + String(Date.now()).slice(-8),
            cliente: { nome: FH.el('simNome').value, telefone: FH.el('simTel').value }, endereco: FH.el('simEnd').value,
            observacao: FH.el('simObs').value,
            itens: carrinho.map(function (c) { return { id_produto: c.id_produto, quantidade: c.quantidade }; }),
            forma_pagamento: FH.el('simPagto').value
        };
        enviar(ultimo);
        carrinho = []; desenharCarrinho(); FH.el('simObs').value = '';
    });
    FH.el('btnRepetir').addEventListener('click', function () { if (ultimo) { enviar(ultimo); } else { FH.toast('Envie um pedido primeiro.', 'info'); } });

    async function carregarMsgs() {
        var tb = FH.el('tbMsgs');
        try {
            var l = await FH.api('api/notificacoes.php');
            tb.innerHTML = l.length ? l.map(function (n) {
                return '<tr><td class="nowrap">' + FH.esc(n.data_hora_envio) + '</td><td>#' + n.id_pedido + '</td><td>' + FH.esc(n.tipo) + '</td><td>' + FH.esc(n.mensagem) +
                    '</td><td><span class="fh-badge ' + (n.status_envio === 'enviado' ? 'ativo' : 'inativo') + '">' + FH.esc(n.status_envio) + '</span></td></tr>';
            }).join('') : '<tr><td colspan="5" class="vazio-msg">Nenhuma mensagem enviada ainda.</td></tr>';
        } catch (e) { tb.innerHTML = '<tr><td colspan="5" class="vazio-msg">' + FH.esc(e.message) + '</td></tr>'; }
    }
    iniciar();
})();
