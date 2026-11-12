/* Taxa de serviço (10%) dos garçons: resumo por garçom, pagamento/repasse e cadastro. */
(function () {
    'use strict';
    var API = 'api/gorjetas.php', per = { de: '', ate: '' };

    function hoje() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }

    async function carregar() {
        per.de = FH.el('gDe').value; per.ate = FH.el('gAte').value;
        try {
            var r = await FH.api(API + '?de=' + encodeURIComponent(per.de) + '&ate=' + encodeURIComponent(per.ate));
            FH.el('gTotal').textContent = FH.moeda(r.totais.total);
            FH.el('gPago').textContent = FH.moeda(r.totais.pago);
            FH.el('gPend').textContent = FH.moeda(r.totais.pendente);

            FH.el('tbGarcons').innerHTML = r.garcons.map(function (g) {
                var botao = g.pendente > 0 && g.id_garcom
                    ? '<button class="btn-fh btn-sm" data-pagar="' + g.id_garcom + '" data-nome="' + FH.esc(g.garcom) + '" data-valor="' + g.pendente + '">Pagar ' + FH.moeda(g.pendente) + '</button>'
                    : (g.pendente > 0 ? '' : '<span class="fh-badge ativo">Tudo pago</span>');
                return '<tr><td>' + FH.esc(g.garcom) + '</td><td class="num">' + g.contas + '</td><td class="num">' + FH.moeda(g.total) +
                    '</td><td class="num">' + FH.moeda(g.pago) + '</td><td class="num"><strong>' + FH.moeda(g.pendente) + '</strong></td><td>' + botao + '</td></tr>';
            }).join('') || '<tr><td colspan="6" class="muted">Nenhuma taxa de serviço neste período.</td></tr>';

            FH.el('tbLanc').innerHTML = r.lancamentos.map(function (l) {
                var sit = l.status === 'pago'
                    ? '<span class="fh-badge ativo">Pago em ' + FH.dataBr(String(l.data_pagamento).substring(0, 10)) + '</span>'
                    : '<span class="fh-badge alerta">A pagar</span>';
                return '<tr><td>' + FH.dataBr(String(l.data_hora).substring(0, 10)) + ' ' + FH.horaBr(l.data_hora) + '</td><td>' + (l.mesa || '—') + '</td><td>' + FH.esc(l.garcom || '—') +
                    '</td><td class="num">' + FH.moeda(l.base) + '</td><td class="num">' + FH.moeda(l.valor) + '</td><td>' + sit + '</td></tr>';
            }).join('') || '<tr><td colspan="6" class="muted">Sem lançamentos.</td></tr>';
        } catch (e) { FH.toast(e.message, 'erro'); }
    }

    FH.el('tbGarcons').addEventListener('click', async function (ev) {
        var b = ev.target.closest('[data-pagar]'); if (!b) { return; }
        if (!await FH.confirmar('Pagar garçom', 'Confirma o pagamento de ' + FH.moeda(b.dataset.valor) + ' a ' + b.dataset.nome + ' (' + per.de + ' a ' + per.ate + ')?', 'Pagar')) { return; }
        try {
            await FH.api(API, { method: 'POST', corpo: { acao: 'pagar', id_garcom: b.dataset.pagar, de: per.de, ate: per.ate } });
            FH.toast('Pagamento registrado para ' + b.dataset.nome + '.', 'ok'); carregar();
        } catch (e) { FH.toast(e.message, 'erro'); }
    });

    /* ── Cadastro de garçons ── */
    async function listarGarcons() {
        var lista = await FH.api(API + '?garcons=1&todos=1');
        FH.el('listaGarcons').innerHTML = lista.map(function (g) {
            return '<li style="display:flex;justify-content:space-between;align-items:center;padding:.35rem 0;border-bottom:1px solid var(--border)"><span>' + FH.esc(g.nome) +
                (g.ativo ? '' : ' <span class="fh-badge">inativo</span>') + '</span><button class="btn-fh-outline btn-sm" data-alt="' + g.id_garcom + '" data-nome="' + FH.esc(g.nome) + '" data-ativo="' + (g.ativo ? 1 : 0) + '">' +
                (g.ativo ? 'Inativar' : 'Reativar') + '</button></li>';
        }).join('');
    }
    FH.el('btnGarcons').addEventListener('click', function () { listarGarcons().then(function () { FH.abrirModal('modalGarcons'); }); });
    FH.el('listaGarcons').addEventListener('click', async function (ev) {
        var b = ev.target.closest('[data-alt]'); if (!b) { return; }
        try { await FH.api(API, { method: 'POST', corpo: { acao: 'salvar_garcom', id_garcom: b.dataset.alt, nome: b.dataset.nome, ativo: b.dataset.ativo === '1' ? 0 : 1 } }); listarGarcons(); }
        catch (e) { FH.toast(e.message, 'erro'); }
    });
    FH.el('formGarcom').addEventListener('submit', async function (ev) {
        ev.preventDefault();
        try { await FH.api(API, { method: 'POST', corpo: { acao: 'salvar_garcom', nome: FH.el('gNome').value } }); FH.el('gNome').value = ''; FH.toast('Garçom cadastrado.', 'ok'); listarGarcons(); }
        catch (e) { FH.toast(e.message, 'erro'); }
    });

    FH.el('gDe').value = FH.el('gAte').value = hoje();
    FH.el('formPeriodo').addEventListener('submit', function (ev) { ev.preventDefault(); carregar(); });
    carregar();
})();
