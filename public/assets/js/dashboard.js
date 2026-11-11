/* RF25 — Dashboard: busca api/dashboard.php e desenha KPIs, barras de status e gráficos Chart.js.
 * Atualiza sozinho a cada 60 s. Se o Chart.js (CDN) não carregar, o resto da tela continua funcionando. */
(function () {
    'use strict';
    var PALETA = ['#b3161b', '#c99a2e', '#1a1a1a', '#e0603a', '#7a5c12', '#8f1015', '#555555'];
    var graficos = {};

    function temDados(v) { return v.some(function (x) { return x > 0; }); }
    function desenhar(idCanvas, idBox, config, valores) {
        FH.el(idBox).classList.toggle('sem-dados', !temDados(valores));
        if (graficos[idCanvas]) { graficos[idCanvas].destroy(); }
        graficos[idCanvas] = new Chart(FH.el(idCanvas), config);
    }
    function corTexto() { return document.body.classList.contains('alto-contraste') ? '#ffffff' : '#2b2b2b'; }

    function graficosChart(g) {
        if (typeof Chart === 'undefined') { FH.toast('Os gráficos precisam de internet (Chart.js).', 'info'); return; }
        Chart.defaults.color = corTexto();
        Chart.defaults.font.family = "'Source Sans 3', system-ui, sans-serif";
        desenhar('grafVendas', 'boxVendas', {
            type: 'bar',
            data: { labels: g.vendas_diarias.labels, datasets: [{ label: 'Vendas', data: g.vendas_diarias.valores, backgroundColor: PALETA[0], borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return ' ' + FH.moeda(c.parsed.y); } } } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: function (v) { return FH.moeda(v); } } } } }
        }, g.vendas_diarias.valores);
        function rosca(id, box, d) {
            desenhar(id, box, { type: 'doughnut',
                data: { labels: d.labels, datasets: [{ data: d.valores, backgroundColor: PALETA, borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '58%', plugins: { legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: function (c) { return ' ' + c.label + ': ' + FH.moeda(c.parsed); } } } } } }, d.valores);
        }
        rosca('grafCanais', 'boxCanais', g.canais);
        rosca('grafFormas', 'boxFormas', g.formas_pagamento);
        desenhar('grafProdutos', 'boxProdutos', {
            type: 'bar',
            data: { labels: g.top_produtos.labels, datasets: [{ label: 'Unidades', data: g.top_produtos.valores, backgroundColor: PALETA[1], borderRadius: 4 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } } }
        }, g.top_produtos.valores);
    }

    function barrasStatus(m) {
        var CLS = { 'novo': 'novo', 'em preparo': 'preparo', 'pronto': 'pronto', 'saiu': 'saiu', 'entregue': 'entregue', 'cancelado': 'cancelado' };
        var max = Math.max.apply(null, Object.keys(m).map(function (k) { return m[k]; }).concat([1]));
        FH.el('barrasStatus').innerHTML = Object.keys(m).map(function (k) {
            return '<div class="status-barra"><span>' + FH.esc(FH.rotuloStatus(k)) + '</span><span class="trilho"><i class="' + CLS[k] + '" style="width:' + (m[k] * 100 / max) + '%"></i></span><strong>' + m[k] + '</strong></div>';
        }).join('');
    }

    function desenharTudo(d) {
        var i = d.indicadores;
        FH.el('kVendas').textContent = FH.moeda(i.vendas_hoje.valor);
        FH.el('kVendasSub').textContent = i.vendas_hoje.pedidos + ' pedido(s) válido(s)';
        FH.el('kTicket').textContent = FH.moeda(i.ticket_medio);
        FH.el('kAbertos').textContent = i.pedidos_em_aberto;
        FH.el('kAtrasados').textContent = i.pedidos_atrasados ? i.pedidos_atrasados + ' atrasado(s) no preparo' : 'nenhum atrasado';
        FH.el('kEstoque').textContent = i.estoque_baixo.quantidade;
        FH.el('cardEstoque').classList.toggle('alerta', i.estoque_baixo.quantidade > 0);
        var f = i.financeiro;
        FH.el('kSaldo').textContent = FH.moeda(f.saldo_mes);
        FH.el('kReceber').textContent = FH.moeda(f.a_receber);
        FH.el('kReceberSub').textContent = f.a_receber_vencido ? FH.moeda(f.a_receber_vencido) + ' já venceu' : 'repasses e recebimentos';
        FH.el('kPagar').textContent = FH.moeda(f.a_pagar);
        FH.el('kPagarSub').textContent = 'contas em aberto';
        FH.el('kVencidas').textContent = f.pagar_vencidas;
        FH.el('kVencidasSub').textContent = f.pagar_vencidas ? FH.moeda(f.a_pagar_vencido) + ' em atraso' : 'tudo em dia';
        FH.el('cardContas').classList.toggle('alerta', f.pagar_vencidas > 0);
        barrasStatus(i.pedidos_por_status);
        FH.el('listaUltimos').innerHTML = i.ultimos_pedidos.length ? i.ultimos_pedidos.map(function (p) {
            return '<li><span>#' + p.id_pedido + ' · ' + FH.esc(p.mesa_numero ? 'Mesa ' + p.mesa_numero : p.canal_rotulo) +
                '<span class="sub">' + FH.horaBr(p.data_hora) + ' · <span class="fh-badge ' + FH.classeStatus(p.status) + '">' + FH.esc(p.status_rotulo) + '</span></span></span><strong>' + FH.moeda(p.valor_total) + '</strong></li>';
        }).join('') : '<li class="muted">Nenhum pedido ainda.</li>';
        FH.el('listaEstoque').innerHTML = i.estoque_baixo.itens.length ? i.estoque_baixo.itens.map(function (x) {
            return '<li><span>' + FH.esc(x.nome) + '<span class="sub">mínimo ' + FH.numero(x.estoque_minimo) + ' ' + FH.esc(x.unidade) + '</span></span><strong>' + FH.numero(x.estoque_atual) + ' ' + FH.esc(x.unidade) + '</strong></li>';
        }).join('') : '<li class="muted">Tudo em dia. ✔</li>';
        graficosChart(d.graficos);
        FH.el('atualizadoEm').textContent = 'Atualizado em ' + d.gerado_em;
    }

    async function carregar() {
        var erro = FH.el('dashErro');
        try { var d = await FH.api('api/dashboard.php'); erro.hidden = true; desenharTudo(d); }
        catch (e) { erro.hidden = false; erro.textContent = e.message; }
    }
    FH.el('btnAtualizar').addEventListener('click', carregar);
    document.addEventListener('fh:contraste', carregar);
    carregar(); setInterval(carregar, 60000);
})();
