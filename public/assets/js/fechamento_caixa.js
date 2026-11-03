/* RF23 — Fechamento de caixa: prévia ao vivo, fechamento e download do PDF. */
(function () {
    'use strict';

    var API = 'api/fechamento_caixa.php';
    var atual = null;
    var modal = new bootstrap.Modal(document.getElementById('modalFechar'));
    function el(id) { return document.getElementById(id); }

    function renderizar(d) {
        atual = d;
        el('resTotal').textContent = FH.moeda(d.total_geral);
        el('resPedidos').textContent = d.quantidade_pedidos;
        el('resTicket').textContent = FH.moeda(d.ticket_medio);

        el('tbForma').innerHTML = d.por_forma.map(function (x) {
            return '<tr><td>' + FH.esc(x.rotulo) + '</td><td class="num">' + FH.moeda(x.total) + '</td><td class="num">' + FH.percentual(x.percentual) + '</td></tr>';
        }).join('') || '<tr><td colspan="3" class="text-secondary">Sem dados.</td></tr>';

        el('tbCanal').innerHTML = d.por_canal.map(function (x) {
            return '<tr><td>' + FH.esc(x.rotulo) + '</td><td class="num">' + x.pedidos + '</td><td class="num">' + FH.moeda(x.total) + '</td><td class="num">' + FH.percentual(x.percentual) + '</td></tr>';
        }).join('') || '<tr><td colspan="4" class="text-secondary">Nenhum pagamento nesta data.</td></tr>';

        // Situação
        el('areaSituacao').innerHTML = d.fechado
            ? '<span class="badge badge-ok fs-6">Caixa fechado' + (d.responsavel ? ' por ' + FH.esc(d.responsavel) : '') + '</span>'
            : '<span class="badge badge-prev fs-6">Prévia — caixa ainda não fechado</span>';

        // Pendências bloqueiam o fechamento
        el('areaPendencias').innerHTML = d.pendencias.length
            ? '<div class="alert alert-warning" role="alert"><strong>' + d.pendencias.length + ' pedido(s) com pagamento pendente</strong> — regularize antes de fechar: ' +
              d.pendencias.map(function (p) { return '#' + p.id_pedido + ' (saldo ' + FH.moeda(p.saldo) + ')'; }).join(', ') + '.</div>'
            : '';

        // Observações: editáveis só antes de fechar
        el('obs').value = d.observacoes || '';
        el('obs').disabled = d.fechado;
        el('btnFechar').disabled = !d.pode_fechar;
        el('btnPdf').href = 'fechamento_caixa_pdf.php?data=' + encodeURIComponent(d.data_referencia);
    }

    async function consultar() {
        try {
            renderizar(await FH.api(API + '?data=' + encodeURIComponent(el('data').value)));
        } catch (e) {
            FH.toast(e.message, 'erro');
        }
    }

    el('formData').addEventListener('submit', function (ev) { ev.preventDefault(); consultar(); });

    el('btnFechar').addEventListener('click', function () {
        el('modalData').textContent = FH.dataBr(atual.data_referencia);
        el('modalTotal').textContent = FH.moeda(atual.total_geral);
        modal.show();
    });

    el('btnConfirmar').addEventListener('click', async function () {
        var botao = this;
        botao.disabled = true; // evita duplo clique
        try {
            var d = await FH.api(API, { method: 'POST', corpo: { data: atual.data_referencia, observacoes: el('obs').value } });
            modal.hide();
            renderizar(d);
            FH.toast('Caixa fechado com sucesso.', 'ok');
        } catch (e) {
            modal.hide();
            FH.toast(e.message, 'erro');
        } finally {
            botao.disabled = false;
        }
    });

    if (window.FH_ERRO_PDF) { FH.toast(window.FH_ERRO_PDF, 'erro'); }
    consultar();
})();
