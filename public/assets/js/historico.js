/* Histórico de pedidos finalizados: filtros, totais e reimpressão da comanda. */
(function () {
    'use strict';
    var FORMAS = { dinheiro: 'Dinheiro', pix: 'Pix', credito: 'Crédito', debito: 'Débito', vale: 'Vale' };
    var lista = [], canaisCarregados = false;

    // Vindo do quadro com ?busca=…
    var qs = new URLSearchParams(location.search);
    if (qs.get('busca')) { FH.el('hBusca').value = qs.get('busca'); }

    function onde(p) { return p.mesa_numero ? 'Mesa ' + p.mesa_numero : p.canal_rotulo; }
    function dataHora(dt) { return FH.dataBr(String(dt).slice(0, 10)) + ' ' + FH.horaBr(dt); }

    async function carregar() {
        var tb = FH.el('tbHist');
        var q = 'api/pedidos.php?visao=historico&de=' + encodeURIComponent(FH.el('hDe').value) + '&ate=' + encodeURIComponent(FH.el('hAte').value) +
            '&canal=' + encodeURIComponent(FH.el('hCanal').value) + '&busca=' + encodeURIComponent(FH.el('hBusca').value.trim());
        try {
            var d = await FH.api(q);
            if (!canaisCarregados) {
                FH.el('hCanal').insertAdjacentHTML('beforeend', Object.keys(d.canais).map(function (c) { return '<option value="' + FH.esc(c) + '">' + FH.esc(d.canais[c]) + '</option>'; }).join(''));
                canaisCarregados = true;
            }
            var st = FH.el('hStatus').value;
            lista = d.pedidos.filter(function (p) { return !st || p.status === st; });
            desenhar(d);
        } catch (e) { tb.innerHTML = '<tr><td colspan="9" class="vazio-msg">' + FH.esc(e.message) + '</td></tr>'; FH.el('tfHist').innerHTML = ''; }
    }

    function desenhar(d) {
        var r = d.resumo;
        FH.el('hResumo').innerHTML =
            '<div><span>Pedidos</span><strong>' + r.quantidade + '</strong></div>' +
            '<div><span>Entregues</span><strong>' + r.entregues + '</strong></div>' +
            '<div><span>Cancelados</span><strong>' + r.cancelados + '</strong></div>' +
            '<div><span>Total vendido</span><strong>' + FH.moeda(r.total) + '</strong></div>' +
            '<div><span>Ticket médio</span><strong>' + FH.moeda(r.ticket_medio) + '</strong></div>' +
            (r.limite_atingido ? '<p class="fh-hint">Mostrando os 500 mais recentes. Use os filtros para refinar.</p>' : '');
        FH.el('tbHist').innerHTML = lista.length ? lista.map(function (p, k) {
            var itens = p.itens.map(function (i) { return i.quantidade + '× ' + FH.esc(i.nome); }).join('<br>');
            var pg = p.pagamentos.length ? p.pagamentos.map(function (x) { return FH.esc(FORMAS[x.forma] || x.forma); }).join(' + ')
                : '<span class="muted">—</span>';
            var sit = p.status === 'cancelado' ? '<span class="fh-badge st-cancelado">Cancelado</span>'
                : (p.quitado ? '<span class="fh-badge ativo">Entregue · pago</span>' : '<span class="fh-badge alerta">Entregue · falta ' + FH.moeda(p.saldo) + '</span>');
            return '<tr' + (p.status === 'cancelado' ? ' class="linha-cancelada"' : '') + '><td><strong>#' + p.id_pedido + '</strong>' +
                (p.id_pedido_externo ? '<br><small class="muted">' + FH.esc(p.id_pedido_externo) + '</small>' : '') + '</td>' +
                '<td class="nowrap">' + dataHora(p.data_hora) + '</td><td>' + FH.esc(onde(p)) + '</td><td>' + FH.esc(p.cliente || '—') + '</td>' +
                '<td class="hist-itens">' + itens + '</td><td class="num"><strong>' + FH.moeda(p.valor_total) + '</strong></td><td>' + pg + '</td><td>' + sit + '</td>' +
                '<td><button type="button" class="btn-fh-outline btn-sm" data-imprimir="' + k + '">🖨 Comanda</button></td></tr>';
        }).join('') : '<tr><td colspan="9" class="vazio-msg">Nenhum pedido finalizado com esses filtros.</td></tr>';
        var soma = lista.filter(function (p) { return p.status !== 'cancelado'; }).reduce(function (s, p) { return s + p.valor_total; }, 0);
        FH.el('tfHist').innerHTML = lista.length ? '<tr><td colspan="5"><strong>Total da lista (sem cancelados)</strong></td><td class="num"><strong>' +
            FH.moeda(Math.round(soma * 100) / 100) + '</strong></td><td colspan="3"></td></tr>' : '';
    }

    FH.el('tbHist').addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-imprimir]'); if (!b) { return; }
        var p = lista[Number(b.dataset.imprimir)];
        FH.imprimirComanda({
            titulo: 'COMANDA — PEDIDO #' + p.id_pedido,
            linhas: [onde(p) + (p.cliente ? ' · ' + p.cliente : ''), 'Pedido em ' + dataHora(p.data_hora)].concat(p.id_pedido_externo ? ['Código: ' + p.id_pedido_externo] : []),
            itens: p.itens, total: p.valor_total, rotuloTotal: p.status === 'cancelado' ? 'TOTAL (CANCELADO)' : 'TOTAL',
            pagamentos: p.pagamentos, observacao: p.observacao,
            carimbo: p.status === 'cancelado' ? 'CANCELADO' : (p.quitado ? 'PAGO' : 'PAGAMENTO PENDENTE')
        });
    });
    FH.el('formHist').addEventListener('submit', function (ev) { ev.preventDefault(); carregar(); });
    FH.el('hCanal').addEventListener('change', carregar);
    FH.el('hStatus').addEventListener('change', carregar);
    carregar();
})();
