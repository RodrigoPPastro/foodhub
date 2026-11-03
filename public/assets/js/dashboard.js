/* RF25 — Dashboard: busca os dados via Fetch e desenha os gráficos com Chart.js. */
(function () {
    'use strict';

    var PALETA = ['#b3161b', '#c99a2e', '#1a1a1a', '#e0603a', '#7a5c12', '#8f1015', '#555555'];
    var INTERVALO_MS = 60000; // atualização automática a cada 60 s
    var graficos = {};
    var ultimoPayload = null;

    function el(id) { return document.getElementById(id); }
    function css(nome) { return getComputedStyle(document.documentElement).getPropertyValue(nome).trim(); }
    function temDados(valores) { return valores.some(function (v) { return v > 0; }); }

    function desenhar(idCanvas, idBox, config, valores) {
        var box = el(idBox);
        box.classList.toggle('sem-dados', !temDados(valores));
        if (graficos[idCanvas]) { graficos[idCanvas].destroy(); }
        graficos[idCanvas] = new Chart(el(idCanvas), config);
    }

    function opcoesBase() {
        var texto = css('--fh-texto'), grade = css('--fh-borda');
        return { texto: texto, grade: grade };
    }

    function renderizarGraficos(g) {
        var c = opcoesBase();
        Chart.defaults.color = c.texto;
        Chart.defaults.font.family = "'Source Sans 3', system-ui, sans-serif";

        var tooltipMoeda = { callbacks: { label: function (ctx) { return ' ' + FH.moeda(ctx.parsed.y); } } };

        desenhar('grafVendas', 'boxVendas', {
            type: 'bar',
            data: { labels: g.vendas_diarias.labels, datasets: [{ label: 'Vendas (R$)', data: g.vendas_diarias.valores, backgroundColor: PALETA[0], borderRadius: 4 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tooltipMoeda },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, grid: { color: c.grade }, ticks: { callback: function (v) { return FH.moeda(v); } } }
                }
            }
        }, g.vendas_diarias.valores);

        function rosca(idCanvas, idBox, dados) {
            desenhar(idCanvas, idBox, {
                type: 'doughnut',
                data: { labels: dados.labels, datasets: [{ data: dados.valores, backgroundColor: PALETA, borderColor: css('--fh-cartao'), borderWidth: 2 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '58%',
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + FH.moeda(ctx.parsed); } } }
                    }
                }
            }, dados.valores);
        }
        rosca('grafCanais', 'boxCanais', g.canais);
        rosca('grafFormas', 'boxFormas', g.formas_pagamento);

        desenhar('grafProdutos', 'boxProdutos', {
            type: 'bar',
            data: { labels: g.top_produtos.labels, datasets: [{ label: 'Unidades', data: g.top_produtos.valores, backgroundColor: PALETA[1], borderRadius: 4 }] },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: c.grade }, ticks: { precision: 0 } },
                    y: { grid: { display: false } }
                }
            }
        }, g.top_produtos.valores);
    }

    function renderizarIndicadores(i) {
        el('kpiVendas').textContent = FH.moeda(i.vendas_hoje.valor);
        el('kpiVendasPedidos').textContent = i.vendas_hoje.pedidos + (i.vendas_hoje.pedidos === 1 ? ' pedido' : ' pedidos');
        el('kpiAbertos').textContent = i.pedidos_em_aberto;
        el('kpiTicket').textContent = FH.moeda(i.ticket_medio);
        el('kpiEstoque').textContent = i.estoque_baixo.quantidade;
        el('cartaoEstoque').classList.toggle('fh-kpi-alerta', i.estoque_baixo.quantidade > 0);

        var corpo = el('tbEstoque');
        if (!i.estoque_baixo.itens.length) {
            corpo.innerHTML = '<tr><td colspan="3" class="text-secondary">Nenhum insumo abaixo do mínimo. 👍</td></tr>';
            return;
        }
        corpo.innerHTML = i.estoque_baixo.itens.map(function (x) {
            return '<tr><td>' + FH.esc(x.nome) + '</td>' +
                '<td class="num text-danger fw-semibold">' + FH.numero(x.estoque_atual) + ' ' + FH.esc(x.unidade) + '</td>' +
                '<td class="num">' + FH.numero(x.estoque_minimo) + ' ' + FH.esc(x.unidade) + '</td></tr>';
        }).join('');
    }

    async function carregar(silencioso) {
        var botao = el('btnAtualizar');
        botao.disabled = true;
        try {
            var dados = await FH.api('api/dashboard.php');
            ultimoPayload = dados;
            renderizarIndicadores(dados.indicadores);
            renderizarGraficos(dados.graficos);
            el('atualizadoEm').textContent = 'Atualizado em ' + dados.gerado_em;
        } catch (e) {
            // Em atualização automática, só avisa uma vez por falha; os dados antigos permanecem na tela.
            FH.toast(e.message, 'erro');
            if (!silencioso) { el('atualizadoEm').textContent = 'Falha ao atualizar'; }
        } finally {
            botao.disabled = false;
        }
    }

    // Recolore os gráficos quando o usuário alterna claro/escuro.
    new MutationObserver(function () {
        if (ultimoPayload) { renderizarGraficos(ultimoPayload.graficos); }
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

    el('btnAtualizar').addEventListener('click', function () { carregar(false); });
    carregar(false);
    setInterval(function () { if (!document.hidden) { carregar(true); } }, INTERVALO_MS);
})();
