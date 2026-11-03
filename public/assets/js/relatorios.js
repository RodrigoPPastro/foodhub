/* RF24 — Relatórios gerenciais. Filtra via AJAX, sem recarregar a página (RNF08). */
(function () {
    'use strict';

    var API = 'api/relatorios.php';
    function el(id) { return document.getElementById(id); }
    function vazio(cols) { return '<tr><td colspan="' + cols + '" class="text-secondary">Nenhum dado no período.</td></tr>'; }
    function linha(celulas) { return '<tr>' + celulas.join('') + '</tr>'; }
    function td(v, num) { return '<td' + (num ? ' class="num"' : '') + '>' + v + '</td>'; }
    function negrito(celulas) { return '<tr class="fw-semibold">' + celulas.join('') + '</tr>'; }

    var renderizadores = {
        vendas: function (d) {
            el('tbVendas').innerHTML = d.linhas.map(function (x) {
                return linha([td(FH.dataBr(x.dia)), td(x.pedidos, 1), td(FH.moeda(x.total), 1)]);
            }).join('') || vazio(3);
            el('tfVendas').innerHTML = negrito([td('Total'), td(d.totais.pedidos, 1), td(FH.moeda(d.totais.total), 1)]);
        },
        produtos: function (d) {
            el('tbProdutos').innerHTML = d.linhas.map(function (x, i) {
                return linha([td(i + 1), td(FH.esc(x.nome)), td(x.quantidade, 1), td(FH.moeda(x.receita), 1)]);
            }).join('') || vazio(4);
        },
        insumos: function (d) {
            el('tbInsumos').innerHTML = d.linhas.map(function (x) {
                return linha([td(FH.esc(x.nome)), td(FH.numero(x.consumo) + ' ' + FH.esc(x.unidade), 1), td(FH.moeda(x.custo), 1)]);
            }).join('') || vazio(3);
            el('tfInsumos').innerHTML = d.linhas.length ? negrito([td('Custo total'), td(''), td(FH.moeda(d.totais.custo), 1)]) : '';
        },
        ticket: function (d) {
            el('tkMedio').textContent = FH.moeda(d.ticket_medio);
            el('tkPedidos').textContent = d.pedidos;
            el('tkTotal').textContent = FH.moeda(d.total);
            el('tbTicket').innerHTML = d.serie.map(function (x) {
                return linha([td(FH.dataBr(x.dia)), td(x.pedidos, 1), td(x.pedidos ? FH.moeda(x.ticket_medio) : '—', 1)]);
            }).join('') || vazio(3);
        },
        canais: function (d) {
            el('tbCanais').innerHTML = d.linhas.map(function (x) {
                var sinal = x.diferenca_vs_lider > 0 ? '+' : '';
                return linha([
                    td(FH.esc(x.rotulo)), td(x.pedidos, 1), td(FH.moeda(x.total), 1), td(FH.moeda(x.ticket_medio), 1),
                    '<td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" role="progressbar" aria-valuenow="' + x.participacao_percentual + '" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:' + x.participacao_percentual + '%"></div></div><span class="small">' + FH.percentual(x.participacao_percentual) + '</span></div></td>',
                    td(x.diferenca_vs_lider === 0 ? '—' : sinal + FH.moeda(x.diferenca_vs_lider), 1),
                    td(x.diferenca_vs_lider === 0 ? '—' : sinal + FH.percentual(x.diferenca_percentual_vs_lider), 1)
                ]);
            }).join('') || vazio(7);
            el('tfCanais').innerHTML = d.linhas.length ? negrito([td('Total'), td(''), td(FH.moeda(d.totais.total), 1), td(''), td(''), td(''), td('')]) : '';
        }
    };

    async function carregar() {
        var botao = el('btnFiltrar');
        botao.disabled = true;
        var params = '&inicio=' + encodeURIComponent(el('inicio').value) +
                     '&fim=' + encodeURIComponent(el('fim').value) +
                     '&limite=' + encodeURIComponent(el('limite').value);
        try {
            var tipos = Object.keys(renderizadores);
            var resultados = await Promise.all(tipos.map(function (t) { return FH.api(API + '?relatorio=' + t + params); }));
            tipos.forEach(function (t, i) { renderizadores[t](resultados[i]); });
        } catch (e) {
            FH.toast(e.message, 'erro');
        } finally {
            botao.disabled = false;
        }
    }

    el('formFiltro').addEventListener('submit', function (ev) { ev.preventDefault(); carregar(); });
    carregar();
})();
