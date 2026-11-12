/* RF23 — Fechamento de caixa: prévia ao vivo, fechamento e download do PDF. */
(function () {
    'use strict';

    var API = 'api/fechamento_caixa.php';
    var atual = null;
    function el(id) { return document.getElementById(id); }

    function renderizar(d) {
        atual = d;
        el('resTotal').textContent = FH.moeda(d.total_geral);
        el('resPedidos').textContent = d.quantidade_pedidos;
        el('resTicket').textContent = FH.moeda(d.ticket_medio);

        el('tbForma').innerHTML = d.por_forma.map(function (x) {
            return '<tr><td>' + FH.esc(x.rotulo) + '</td><td class="num">' + FH.moeda(x.total) + '</td><td class="num">' + FH.percentual(x.percentual) + '</td></tr>';
        }).join('') || '<tr><td colspan="3" class="muted">Sem dados.</td></tr>';

        el('tbCanal').innerHTML = d.por_canal.map(function (x) {
            return '<tr><td>' + FH.esc(x.rotulo) + '</td><td class="num">' + x.pedidos + '</td><td class="num">' + FH.moeda(x.total) + '</td><td class="num">' + FH.percentual(x.percentual) + '</td></tr>';
        }).join('') || '<tr><td colspan="4" class="muted">Nenhum pagamento nesta data.</td></tr>';

        // Situação
        el('areaSituacao').innerHTML = d.fechado
            ? '<span class="fh-badge ativo">Caixa fechado' + (d.responsavel ? ' por ' + FH.esc(d.responsavel) : '') + '</span>'
            : '<span class="fh-badge st-em-preparo">Prévia — caixa ainda não fechado</span>';

        // Pendências bloqueiam o fechamento
        el('areaPendencias').innerHTML = d.pendencias.length
            ? '<div class="fh-alert aviso" role="alert"><strong>' + d.pendencias.length + ' pedido(s) com pagamento pendente</strong> — regularize antes de fechar: ' +
              d.pendencias.map(function (p) { return '#' + p.id_pedido + ' (saldo ' + FH.moeda(p.saldo) + ')'; }).join(', ') + '.</div>'
            : '';

        // Observações: editáveis só antes de fechar
        el('obs').value = d.observacoes || '';
        el('obs').disabled = d.fechado;
        el('btnFechar').disabled = !d.pode_fechar;
        el('btnPdf').href = 'fechamento_caixa_pdf.php?data=' + encodeURIComponent(d.data_referencia);
    }

    /* Resumo informativo da taxa de serviço do dia (fica fora do total de vendas). */
    async function taxaDoDia() {
        try {
            var d = el('data').value, r = await FH.api('api/gorjetas.php?de=' + encodeURIComponent(d) + '&ate=' + encodeURIComponent(d));
            el('txTotal').textContent = FH.moeda(r.totais.total);
            el('txPaga').textContent = FH.moeda(r.totais.pago);
            el('txPend').textContent = FH.moeda(r.totais.pendente);
        } catch (e) { /* bloco informativo: não atrapalha o fechamento */ }
    }

    async function consultar() {
        taxaDoDia();
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
        FH.abrirModal('modalFechar');
    });

    el('btnConfirmar').addEventListener('click', async function () {
        var botao = this;
        botao.disabled = true; // evita duplo clique
        try {
            var d = await FH.api(API, { method: 'POST', corpo: { data: atual.data_referencia, observacoes: el('obs').value } });
            FH.fecharModal('modalFechar');
            renderizar(d);
            FH.toast('Caixa fechado com sucesso.', 'ok');
        } catch (e) {
            FH.fecharModal('modalFechar');
            FH.toast(e.message, 'erro');
        } finally {
            botao.disabled = false;
        }
    });

    if (window.FH_ERRO_PDF) { FH.toast(window.FH_ERRO_PDF, 'erro'); }
    consultar();
})();
