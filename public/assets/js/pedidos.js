/* Kanban de pedidos (RF17–RF22). Toda regra (fluxo de status, estoque, preços) é validada no servidor;
 * aqui só mostramos os botões que o servidor diz serem possíveis (campo "proximos"). */
(function () {
    'use strict';
    var COLS = [['novo', 'Novo'], ['em preparo', 'Em preparo'], ['pronto', 'Pronto'], ['saiu', 'Saiu p/ entrega'], ['entregue', 'Entregue']];
    var kanban = FH.el('kanban'), dados = null, cardapio = [], mesas = [], entregadores = [], porId = {};
    var pendente = null;   // pedido aguardando escolha de entregador / pagamento

    /* ── Quadro ───────────────────────────────────────────────────── */
    /** Onde cada item está: setor de preparo e andamento (ex.: "Bar · Em preparo", "✓ servido"). */
    function andamentoItem(i) {
        if (i.setor_preparo === 'nenhum') {
            return ' <span class="st-item ok">' + (i.status_item === 'entregue' ? '✓ servido' : '✓ pronta entrega') + '</span>';
        }
        var ok = i.status_item === 'pronto' || i.status_item === 'entregue';
        return ' <span class="st-item' + (ok ? ' ok' : '') + '">· ' + FH.esc(i.setor_rotulo) + ': ' + FH.esc(i.status_item_rotulo) + '</span>';
    }
    function cartao(p) {
        var onde = p.mesa_numero ? 'Mesa ' + p.mesa_numero + (p.cliente ? ' · ' + p.cliente : '') : p.canal_rotulo;
        var ehMesa = !!p.mesa_numero, fechada = ehMesa && p.quitado;
        var emAndamento = p.status === 'novo' || p.status === 'em preparo';
        var itens = p.itens.map(function (i) { return '<li>' + i.quantidade + '× ' + FH.esc(i.nome) + (emAndamento ? andamentoItem(i) : '') + '</li>'; }).join('');
        var btns = p.proximos.map(function (s) {
            var cancel = s === 'cancelado';
            return '<button class="' + (cancel ? 'btn-fh-ghost' : 'btn-fh') + ' btn-sm" data-acao="status" data-id="' + p.id_pedido + '" data-st="' + s + '">' +
                (cancel ? 'Cancelar' : '→ ' + FH.esc(FH.rotuloStatus(s))) + '</button>';
        }).join('');
        if (!p.quitado && p.status !== 'cancelado') { btns += '<button class="btn-fh-gold btn-sm" data-acao="pagar" data-id="' + p.id_pedido + '">Pagar</button>'; }
        var meta = '<span>' + FH.horaBr(p.data_hora) + '</span>';
        if (p.cliente && !ehMesa) { meta += '<span>' + FH.esc(p.cliente) + '</span>'; }
        if (p.entregador) { meta += '<span>🛵 ' + FH.esc(p.entregador) + '</span>'; }
        if (p.plataforma_externa && p.id_pedido_externo) { meta += '<span>' + FH.esc(p.id_pedido_externo) + '</span>'; }
        // Mesa: a etiqueta diz se a conta daquele pedido já foi fechada (vermelho) ou ainda está aberta (verde)
        var etiqueta = ehMesa
            ? (fechada ? '<span class="tag-conta fechada">Fechada</span>' : '<span class="tag-conta aberta">Aberta · a pagar ' + FH.moeda(p.saldo) + '</span>')
            : (p.quitado ? '<span class="fh-badge ativo">Pago</span>' : '<span class="fh-badge alerta">Falta ' + FH.moeda(p.saldo) + '</span>');
        var classeConta = !ehMesa || p.status === 'cancelado' ? '' : (fechada ? ' conta-fechada' : ' conta-aberta');
        return '<div class="k-card ' + (p.atrasado ? 'atrasado' : '') + classeConta + '"><div class="topo"><strong>#' + p.id_pedido + '</strong><span class="canal-tag">' + FH.esc(onde) + '</span></div>' +
            (p.atrasado ? '<span class="tag-atraso">ATRASADO · ' + p.minutos_em_preparo + ' min</span>' : '') +
            '<ul class="itens">' + itens + '</ul>' + (p.endereco_entrega && p.status !== 'entregue' ? '<div class="fh-hint">📍 ' + FH.esc(p.endereco_entrega) + '</div>' : '') +
            '<div class="meta">' + meta + '</div>' +
            '<div><strong>' + FH.moeda(p.valor_total) + '</strong> ' + etiqueta + '</div>' +
            '<div class="acoes">' + btns + '</div></div>';
    }
    /** Linha curta de um pedido finalizado (clique abre o histórico já filtrado). */
    function linhaFinalizado(p) {
        var onde = p.mesa_numero ? 'Mesa ' + p.mesa_numero : p.canal_rotulo;
        var etiqueta = p.mesa_numero ? '<span class="tag-conta fechada">Fechada</span>' : '<span class="fh-badge ativo">Pago</span>';
        return '<a class="k-linha" href="historico.php?busca=' + p.id_pedido + '" title="Ver no histórico"><strong>#' + p.id_pedido + '</strong>' +
            '<span class="onde">' + FH.esc(onde) + (p.cliente ? ' · ' + FH.esc(p.cliente) : '') + '</span>' +
            '<span class="valor">' + FH.moeda(p.valor_total) + '</span>' + etiqueta + '</a>';
    }
    /**
     * Coluna "Entregue": em cima, os que ainda têm conta a cobrar (cartão completo);
     * embaixo, só os 5 últimos finalizados em uma linha + link para o histórico do dia.
     */
    function colunaEntregue(l) {
        var aCobrar = l.filter(function (p) { return !p.quitado; });
        var finalizados = l.filter(function (p) { return p.quitado; }).slice().reverse();   // mais recentes primeiro
        var html = aCobrar.map(cartao).join('');
        if (finalizados.length) {
            html += '<div class="k-sub">Finalizados hoje</div>' + finalizados.slice(0, 5).map(linhaFinalizado).join('') +
                '<a class="k-ver-todos" href="historico.php">' + (finalizados.length > 5 ? 'Ver todos os ' + finalizados.length + ' de hoje →' : 'Ver histórico →') + '</a>';
        }
        return html || '<p class="muted" style="text-align:center;font-size:.85rem">Vazio</p>';
    }
    function desenhar() {
        porId = {};
        kanban.innerHTML = COLS.map(function (c) {
            var l = dados.colunas[c[0]] || [];
            l.forEach(function (p) { porId[p.id_pedido] = p; });
            var corpo = c[0] === 'entregue' ? colunaEntregue(l)
                : (l.length ? l.map(cartao).join('') : '<p class="muted" style="text-align:center;font-size:.85rem">Vazio</p>');
            return '<section class="k-col c-' + c[0].replace(' ', '-') + '"><header><span>' + c[1] + '</span><span class="cnt">' + l.length + '</span></header>' +
                '<div class="k-corpo">' + corpo + '</div></section>';
        }).join('');
        FH.el('pedAtualizado').textContent = '· atualizado às ' + dados.gerado_em;
    }
    async function carregar() {
        var canal = FH.el('filtroCanal').value;
        try { dados = await FH.api('api/pedidos.php?visao=quadro' + (canal ? '&canal=' + encodeURIComponent(canal) : '')); desenhar(); }
        catch (e) { kanban.innerHTML = '<p class="vazio-msg">' + FH.esc(e.message) + '</p>'; }
    }

    /* ── Mudança de status ────────────────────────────────────────── */
    async function mudar(id, st, idEntregador) {
        var corpo = { acao: 'status', id_pedido: id, status: st };
        if (idEntregador) { corpo.id_entregador = idEntregador; }
        try { await FH.api('api/pedidos.php', { method: 'POST', corpo: corpo }); FH.toast('Pedido #' + id + ' → ' + FH.rotuloStatus(st) + '.', 'ok'); }
        catch (e) { FH.toast(e.message, 'erro'); }
        carregar();
    }
    kanban.addEventListener('click', async function (ev) {
        var b = ev.target.closest('button[data-acao]'); if (!b) { return; }
        var id = Number(b.dataset.id), p = porId[id];
        if (b.dataset.acao === 'pagar') { abrirPagto(p); return; }
        var st = b.dataset.st;
        if (st === 'cancelado' && !await FH.confirmar('Cancelar pedido', 'Cancelar o pedido #' + id + '? O estoque consumido será devolvido.', 'Cancelar pedido')) { return; }
        if (st === 'saiu') { await abrirEntregador(p); return; }
        mudar(id, st);
    });

    async function abrirEntregador(p) {
        pendente = p;
        try { entregadores = await FH.api('api/entregadores.php'); } catch (e) { FH.toast(e.message, 'erro'); return; }
        var livres = entregadores.filter(function (e) { return e.status === 'disponivel' || e.id_entregador === p.id_entregador; });
        if (!livres.length) { FH.toast('Nenhum entregador disponível. Cadastre ou libere um entregador.', 'erro'); return; }
        FH.el('selEntregador').innerHTML = livres.map(function (e) {
            return '<option value="' + e.id_entregador + '"' + (e.id_entregador === p.id_entregador ? ' selected' : '') + '>' + FH.esc(e.nome) + (e.veiculo ? ' (' + FH.esc(e.veiculo) + ')' : '') + '</option>';
        }).join('');
        FH.abrirModal('modalEntregador');
    }
    FH.el('formEnt').addEventListener('submit', function (ev) {
        ev.preventDefault(); FH.fecharModal('modalEntregador');
        mudar(pendente.id_pedido, 'saiu', Number(FH.el('selEntregador').value));
    });

    /* ── Pagamento de pedido existente ────────────────────────────── */
    var pgSaldo = 0;
    var pgUI = FH.pagamentoUI({ lista: FH.el('pgLinhas'), aoMudar: function () {
        FH.el('pgInformado').textContent = FH.moeda(pgUI.soma());
        FH.el('pgOk').disabled = Math.abs(pgUI.soma() - pgSaldo) > 0.004;
    } });
    function abrirPagto(p) {
        pendente = p; pgSaldo = p.saldo;
        FH.el('tituloPagto').textContent = 'Pagamento do pedido #' + p.id_pedido;
        FH.el('pgSaldo').textContent = FH.moeda(p.saldo);
        pgUI.reiniciar(p.saldo); FH.abrirModal('modalPagto');
    }
    FH.el('pgAdd').addEventListener('click', function () { pgUI.adicionar('pix', Math.max(0, Math.round((pgSaldo - pgUI.soma()) * 100) / 100)); });
    FH.el('pgOk').addEventListener('click', async function () {
        try { await FH.api('api/pedidos.php', { method: 'POST', corpo: { acao: 'pagar', id_pedido: pendente.id_pedido, pagamentos: pgUI.pagamentos() } });
              FH.fecharModal('modalPagto'); FH.toast('Pagamento registrado.', 'ok'); carregar(); }
        catch (e) { FH.toast(e.message, 'erro'); }
    });

    /* ── Novo pedido ──────────────────────────────────────────────── */
    var pedUI = FH.pagamentoUI({ lista: FH.el('pedPagamentos'), aoMudar: function () {} });
    function totalPedido() {
        var t = 0;
        FH.el('pedItens').querySelectorAll('input[data-preco]').forEach(function (i) { t += (Number(i.value) || 0) * Number(i.dataset.preco); });
        return Math.round(t * 100) / 100;
    }
    function atualizarTotal() { FH.el('pedTotal').textContent = FH.moeda(totalPedido()); }
    function ajustarCanal() {
        var c = FH.el('pedCanal').value;
        FH.el('grpMesa').hidden = c !== 'mesa';
        FH.el('grpMesaNome').hidden = c !== 'mesa';
        sugerirNomes();
        FH.el('grpEntrega').hidden = c !== 'delivery_proprio';
    }
    /** Sugere os nomes que já têm pedido em aberto na mesa escolhida (para lançar mais uma rodada para a mesma pessoa). */
    function sugerirNomes() {
        var m = mesas.find(function (x) { return String(x.id_mesa) === FH.el('pedMesa').value; });
        var nomes = [];
        (m ? m.pedidos : []).forEach(function (p) { if (p.cliente && nomes.indexOf(p.cliente) < 0) { nomes.push(p.cliente); } });
        FH.el('nomesDaMesa').innerHTML = nomes.map(function (n) { return '<option value="' + FH.esc(n) + '">'; }).join('');
    }
    async function abrirNovo() {
        try {
            if (!cardapio.length) { cardapio = await FH.api('api/produtos.php?visao=cardapio'); }
            mesas = await FH.api('api/mesas.php');
        } catch (e) { FH.toast(e.message, 'erro'); return; }
        FH.el('pedItens').innerHTML = cardapio.map(function (g) {
            return '<strong style="font-size:.8rem;color:var(--ink-soft)">' + FH.esc(g.categoria) + '</strong>' + g.produtos.map(function (p) {
                return '<div class="item-pick"><div class="nome">' + FH.esc(p.nome) + '<small>' + FH.moeda(p.preco_venda) + '</small></div>' +
                    '<input type="number" min="0" max="99" value="0" data-id="' + p.id_produto + '" data-preco="' + p.preco_venda + '" aria-label="Quantidade de ' + FH.esc(p.nome) + '"></div>';
            }).join('');
        }).join('');
        FH.el('pedMesa').innerHTML = mesas.map(function (m) { return '<option value="' + m.id_mesa + '">Mesa ' + m.numero + (m.status === 'ocupada' ? ' (ocupada)' : '') + '</option>'; }).join('');
        ['pedNome', 'pedTel', 'pedEnd', 'pedObs', 'pedMesaNome'].forEach(function (i) { FH.el(i).value = ''; });
        FH.el('pedCanal').value = 'balcao'; FH.el('pedPagarAgora').checked = false; FH.el('boxPagto').hidden = true;
        ajustarCanal(); atualizarTotal(); FH.abrirModal('modalPedido');
    }
    FH.el('btnNovoPedido').addEventListener('click', abrirNovo);
    FH.el('pedCanal').addEventListener('change', ajustarCanal);
    FH.el('pedMesa').addEventListener('change', sugerirNomes);
    FH.el('pedItens').addEventListener('input', function () { atualizarTotal(); if (!FH.el('boxPagto').hidden) { pedUI.reiniciar(totalPedido()); } });
    FH.el('pedPagarAgora').addEventListener('change', function (e) {
        FH.el('boxPagto').hidden = !e.target.checked;
        if (e.target.checked) { pedUI.reiniciar(totalPedido()); }
    });
    FH.el('pedAddPagto').addEventListener('click', function () { pedUI.adicionar('pix', Math.max(0, Math.round((totalPedido() - pedUI.soma()) * 100) / 100)); });
    FH.el('formPedido').addEventListener('submit', async function (ev) {
        ev.preventDefault();
        var itens = [];
        FH.el('pedItens').querySelectorAll('input[data-id]').forEach(function (i) { var q = Number(i.value) || 0; if (q > 0) { itens.push({ id_produto: Number(i.dataset.id), quantidade: q }); } });
        if (!itens.length) { FH.toast('Escolha pelo menos um item.', 'erro'); return; }
        var canal = FH.el('pedCanal').value;
        var corpo = { acao: 'criar', canal: canal, itens: itens, observacao: FH.el('pedObs').value };
        if (canal === 'mesa') { corpo.id_mesa = Number(FH.el('pedMesa').value); corpo.nome_cliente = FH.el('pedMesaNome').value; }
        if (canal === 'delivery_proprio') { corpo.nome_cliente = FH.el('pedNome').value; corpo.telefone_cliente = FH.el('pedTel').value; corpo.endereco_entrega = FH.el('pedEnd').value; }
        if (FH.el('pedPagarAgora').checked) { corpo.pagamentos = pedUI.pagamentos(); }
        try { var r = await FH.api('api/pedidos.php', { method: 'POST', corpo: corpo }); FH.fecharModal('modalPedido'); FH.toast('Pedido criado.', 'ok'); carregar(); }
        catch (e) { FH.toast(e.message, 'erro'); }
    });

    /* ── Modo compacto (lembrado neste navegador) ─────────────────── */
    function aplicarCompacto(ligado) {
        kanban.classList.toggle('compacto', ligado);
        FH.el('btnCompacto').setAttribute('aria-pressed', ligado ? 'true' : 'false');
        FH.el('btnCompacto').classList.toggle('ativo', ligado);
        try { localStorage.setItem('fh_kanban_compacto', ligado ? '1' : '0'); } catch (e) { /* sem storage */ }
    }
    var compactoSalvo = false;
    try { compactoSalvo = localStorage.getItem('fh_kanban_compacto') === '1'; } catch (e) { /* sem storage */ }
    aplicarCompacto(compactoSalvo);
    FH.el('btnCompacto').addEventListener('click', function () { aplicarCompacto(!kanban.classList.contains('compacto')); });

    /* ── Início ───────────────────────────────────────────────────── */
    FH.el('filtroCanal').addEventListener('change', carregar);
    (async function () {
        await carregar();
        if (dados) {
            FH.el('filtroCanal').insertAdjacentHTML('beforeend', Object.keys(dados.canais).map(function (c) { return '<option value="' + FH.esc(c) + '">' + FH.esc(dados.canais[c]) + '</option>'; }).join(''));
        }
    })();
    setInterval(function () { if (!document.querySelector('.fh-overlay.aberto')) { carregar(); } }, 15000);
})();
