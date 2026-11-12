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
        if (p.garcom) { meta += '<span>🍽 ' + FH.esc(p.garcom) + '</span>'; }
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
        var finalizados = l.filter(function (p) { return p.quitado; });   // a API já traz o mais novo primeiro
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
    var prodPed = {}, carrinhoPed = {}, catPed = 0;   // catálogo por id, itens {id: qtd}, categoria escolhida (0 = todas)

    function totalPedido() {
        var t = 0;
        Object.keys(carrinhoPed).forEach(function (id) { t += carrinhoPed[id] * Number(prodPed[id].preco_venda); });
        return Math.round(t * 100) / 100;
    }
    function atualizarTotal() {
        FH.el('pedTotal').textContent = FH.moeda(totalPedido());
        if (!FH.el('boxPagto').hidden) { pedUI.reiniciar(totalPedido()); }
    }
    var ICONE_FOTO = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 3h16v18l-3-2-2.5 2-2.5-2-2.5 2L7 19l-3 2z"/><path d="M8 8h8M8 12h8"/></svg>';

    /* Esquerda: categorias (chips) + grade de fotos. Clique na foto = +1 no pedido. */
    function desenharCardapio() {
        var busca = FH.el('pedBusca').value.trim().toLowerCase();
        FH.el('pedCats').innerHTML = '<button type="button" class="chip ' + (!catPed ? 'ativa' : '') + '" data-cat="0">Todas</button>' + cardapio.map(function (g) {
            return '<button type="button" class="chip ' + (catPed === g.id_categoria ? 'ativa' : '') + '" data-cat="' + g.id_categoria + '">' + FH.esc(g.categoria) + '</button>';
        }).join('');
        var html = '';
        cardapio.forEach(function (g) {
            if (catPed && g.id_categoria !== catPed) { return; }
            var lista = g.produtos.filter(function (p) { return !busca || p.nome.toLowerCase().indexOf(busca) >= 0; });
            if (!lista.length) { return; }
            html += '<h4 class="np-titulo">' + FH.esc(g.categoria) + '</h4><div class="np-fotos">' + lista.map(function (p) {
                var qtd = carrinhoPed[p.id_produto] || 0;
                var foto = p.foto ? '<img src="' + FH.esc(p.foto) + '" alt="" loading="lazy">' : ICONE_FOTO;
                return '<button type="button" class="np-prod' + (qtd ? ' no-pedido' : '') + '" data-add="' + p.id_produto + '" aria-label="Adicionar ' + FH.esc(p.nome) + '">' +
                    (qtd ? '<span class="np-qtd">' + qtd + '</span>' : '') + '<span class="np-foto">' + foto + '</span>' +
                    '<span class="np-nome">' + FH.esc(p.nome) + '</span><span class="np-preco">' + FH.moeda(p.preco_venda) + '</span></button>';
            }).join('') + '</div>';
        });
        FH.el('pedGrade').innerHTML = html || '<p class="vazio-msg">Nenhum produto encontrado.</p>';
    }

    /* Direita: itens do pedido com − / + e remover. */
    function desenharItens() {
        var ids = Object.keys(carrinhoPed);
        FH.el('pedItens').innerHTML = ids.length ? ids.map(function (id) {
            var p = prodPed[id], q = carrinhoPed[id];
            return '<div class="np-linha"><span class="np-l-nome">' + FH.esc(p.nome) + '<small>' + FH.moeda(p.preco_venda) + ' un.</small></span>' +
                '<span class="np-l-qtd"><button type="button" data-menos="' + id + '" aria-label="Diminuir ' + FH.esc(p.nome) + '">−</button><b>' + q + '</b>' +
                '<button type="button" data-mais="' + id + '" aria-label="Aumentar ' + FH.esc(p.nome) + '">+</button></span>' +
                '<strong class="np-l-sub">' + FH.moeda(q * p.preco_venda) + '</strong>' +
                '<button type="button" class="np-x" data-rem="' + id + '" aria-label="Remover ' + FH.esc(p.nome) + '">×</button></div>';
        }).join('') : '<p class="vazio-msg">Clique na foto de um produto para incluir.</p>';
        atualizarTotal(); desenharCardapio();
    }
    function mudarQtd(id, delta) {
        var q = (carrinhoPed[id] || 0) + delta;
        if (q > 99) { q = 99; }
        if (q <= 0) { delete carrinhoPed[id]; } else { carrinhoPed[id] = q; }
        desenharItens();
    }

    function ajustarCanal() {
        var c = FH.el('pedCanal').value;
        FH.el('grpMesa').hidden = c !== 'mesa';
        FH.el('grpMesaNome').hidden = c !== 'mesa';
        FH.el('grpMesaGarcom').hidden = c !== 'mesa' || !FH.el('pedGarcom').options.length;
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
    /** Garçons ativos para escolher quem atende a mesa (se o usuário não tiver acesso, o campo simplesmente não aparece). */
    async function carregarGarcons() {
        try {
            var lista = await FH.api('api/gorjetas.php?garcons=1');
            FH.el('pedGarcom').innerHTML = '<option value="">— sem garçom —</option>' + lista.map(function (g) { return '<option value="' + g.id_garcom + '">' + FH.esc(g.nome) + '</option>'; }).join('');
        } catch (e) { FH.el('pedGarcom').innerHTML = ''; }
    }
    /** Se a mesa já tem garçom (pedido anterior), já vem selecionado. */
    function garcomDaMesa() {
        var m = mesas.find(function (x) { return String(x.id_mesa) === FH.el('pedMesa').value; });
        if (FH.el('pedGarcom').options.length) { FH.el('pedGarcom').value = m && m.id_garcom ? String(m.id_garcom) : ''; }
    }
    async function abrirNovo() {
        try {
            if (!cardapio.length) { cardapio = await FH.api('api/produtos.php?visao=cardapio'); }
            mesas = await FH.api('api/mesas.php');
        } catch (e) { FH.toast(e.message, 'erro'); return; }
        prodPed = {}; carrinhoPed = {}; catPed = 0; FH.el('pedBusca').value = '';
        cardapio.forEach(function (g) { g.produtos.forEach(function (p) { prodPed[p.id_produto] = p; }); });
        FH.el('pedMesa').innerHTML = mesas.map(function (m) { return '<option value="' + m.id_mesa + '">Mesa ' + m.numero + (m.status === 'ocupada' ? ' (ocupada)' : '') + '</option>'; }).join('');
        ['pedNome', 'pedTel', 'pedEnd', 'pedObs', 'pedMesaNome'].forEach(function (i) { FH.el(i).value = ''; });
        FH.el('pedCanal').value = 'balcao'; FH.el('pedPagarAgora').checked = false; FH.el('boxPagto').hidden = true;
        await carregarGarcons(); garcomDaMesa();
        ajustarCanal(); desenharItens(); FH.abrirModal('modalPedido');
    }
    FH.el('btnNovoPedido').addEventListener('click', abrirNovo);
    FH.el('pedCanal').addEventListener('change', ajustarCanal);
    FH.el('pedMesa').addEventListener('change', function () { sugerirNomes(); garcomDaMesa(); });
    FH.el('pedBusca').addEventListener('input', desenharCardapio);
    FH.el('pedCats').addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-cat]'); if (!b) { return; }
        catPed = Number(b.dataset.cat); desenharCardapio();
    });
    FH.el('pedGrade').addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-add]'); if (b) { mudarQtd(b.dataset.add, 1); }
    });
    FH.el('pedItens').addEventListener('click', function (ev) {
        var b = ev.target.closest('button'); if (!b) { return; }
        if (b.dataset.mais) { mudarQtd(b.dataset.mais, 1); }
        if (b.dataset.menos) { mudarQtd(b.dataset.menos, -1); }
        if (b.dataset.rem) { mudarQtd(b.dataset.rem, -999); }
    });
    FH.el('pedPagarAgora').addEventListener('change', function (e) {
        FH.el('boxPagto').hidden = !e.target.checked;
        if (e.target.checked) { pedUI.reiniciar(totalPedido()); }
    });
    FH.el('pedAddPagto').addEventListener('click', function () { pedUI.adicionar('pix', Math.max(0, Math.round((totalPedido() - pedUI.soma()) * 100) / 100)); });
    FH.el('formPedido').addEventListener('submit', async function (ev) {
        ev.preventDefault();
        var itens = Object.keys(carrinhoPed).map(function (id) { return { id_produto: Number(id), quantidade: carrinhoPed[id] }; });
        if (!itens.length) { FH.toast('Escolha pelo menos um item.', 'erro'); return; }
        var canal = FH.el('pedCanal').value;
        var corpo = { acao: 'criar', canal: canal, itens: itens, observacao: FH.el('pedObs').value };
        if (canal === 'mesa') { corpo.id_mesa = Number(FH.el('pedMesa').value); corpo.nome_cliente = FH.el('pedMesaNome').value; corpo.id_garcom = Number(FH.el('pedGarcom').value) || null; }
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
