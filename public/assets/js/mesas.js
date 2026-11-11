/* Tela de mesas (RF22): ocupação, cadastro e fechamento de conta com pagamento dividido. */
(function () {
    'use strict';
    var grid = FH.el('gridMesas'), mesas = [], atual = null, total = 0;

    var pg = FH.pagamentoUI({ lista: FH.el('contaPagamentos'), aoMudar: atualizarResumo });

    function atualizarResumo() {
        var inf = pg.soma(), falta = Math.round((total - inf) * 100) / 100;
        FH.el('contaTotal').textContent = FH.moeda(total);
        FH.el('contaInformado').textContent = FH.moeda(inf);
        FH.el('contaFalta').textContent = FH.moeda(falta);
        FH.el('btnFecharConta').disabled = Math.abs(falta) > 0.004 || total <= 0;
    }

    async function carregar() {
        try { mesas = await FH.api('api/mesas.php'); desenhar(); }
        catch (e) { grid.innerHTML = '<p class="vazio-msg">' + FH.esc(e.message) + '</p>'; }
    }
    function desenhar() {
        if (!mesas.length) { grid.innerHTML = '<p class="vazio-msg">Nenhuma mesa cadastrada.</p>'; return; }
        grid.innerHTML = mesas.map(function (m) {
            var ocup = m.status === 'ocupada';
            return '<div class="mesa-card ' + m.status + '"><div class="muted">Mesa</div><div class="numero">' + m.numero + '</div>' +
                '<div>' + m.capacidade + ' lugares</div>' +
                '<div><span class="fh-badge ' + (ocup ? 'alerta' : 'ativo') + '">' + (ocup ? 'Ocupada' : 'Livre') + '</span></div>' +
                (ocup ? '<div style="margin-top:.4rem"><strong>' + FH.moeda(m.saldo) + '</strong> a pagar<br><small class="muted">' + m.pedidos.length + ' pedido(s)' +
                    (nomes(m) ? ' · ' + FH.esc(nomes(m)) : '') + '</small></div>' : '') +
                '<div class="acoes">' + (ocup ? '<button class="btn-fh btn-sm" data-conta="' + m.id_mesa + '">Fechar conta</button>' : '') +
                '<button class="btn-fh-outline btn-sm" data-ed="' + m.id_mesa + '">Editar</button>' +
                '<button class="btn-fh-ghost btn-sm" data-ex="' + m.id_mesa + '">Excluir</button></div></div>';
        }).join('');
    }
    function nomes(m) {
        var n = [];
        m.pedidos.forEach(function (p) { if (p.cliente && n.indexOf(p.cliente) < 0) { n.push(p.cliente); } });
        return n.join(', ');
    }
    function rotuloPedido(p) { return '#' + p.id_pedido + (p.cliente ? ' ' + p.cliente : ''); }
    function achar(id) { return mesas.find(function (m) { return m.id_mesa == id; }); }

    function abrirMesa(m) {
        FH.el('tituloMesa').textContent = m ? 'Editar mesa' : 'Nova mesa';
        FH.el('mesaId').value = m ? m.id_mesa : ''; FH.el('mesaNumero').value = m ? m.numero : ''; FH.el('mesaCap').value = m ? m.capacidade : 4;
        FH.abrirModal('modalMesa');
    }
    function abrirConta(m) {
        atual = m; total = m.saldo;
        FH.el('tituloConta').textContent = 'Conta da mesa ' + m.numero;
        FH.el('contaInfo').textContent = m.pedidos.length + ' pedido(s): ' + m.pedidos.map(rotuloPedido).join(', ') +
            (m.aberta_desde ? ' · aberta às ' + FH.horaBr(m.aberta_desde) : '');
        FH.el('contaItens').innerHTML = m.consumo.length ? m.consumo.map(function (i) {
            return '<tr><td class="num">' + i.quantidade + '</td><td>' + FH.esc(i.nome) + '</td><td class="num">' + FH.moeda(i.preco_unitario) +
                '</td><td class="num"><strong>' + FH.moeda(i.subtotal) + '</strong></td></tr>';
        }).join('') : '<tr><td colspan="4" class="vazio-msg">Nenhum item.</td></tr>';
        FH.el('contaConsumido').textContent = FH.moeda(m.total_consumido);
        FH.el('contaJaPago').textContent = '− ' + FH.moeda(m.ja_pago);
        FH.el('linhaJaPago').hidden = !(m.ja_pago > 0);
        pg.reiniciar(total);
        FH.abrirModal('modalConta');
    }

    /* ── Conta impressa (pré-conta para levar à mesa) ─────────────────── */
    var FORMAS = { dinheiro: 'Dinheiro', pix: 'Pix', credito: 'Crédito', debito: 'Débito', vale: 'Vale' };
    /** @param fechada pagamentos já registrados → comprovante "CONTA FECHADA"; sem ele → pré-conta para conferência */
    function imprimirConta(m, fechada) {
        var agora = new Date();
        var linhas = m.consumo.map(function (i) {
            return '<tr><td>' + i.quantidade + 'x</td><td>' + FH.esc(i.nome) + '<br><small>' + FH.moeda(i.preco_unitario) + ' un.</small></td><td class="v">' + FH.moeda(i.subtotal) + '</td></tr>';
        }).join('');
        var pags = (fechada || pg.pagamentos()).filter(function (p) { return Number(p.valor) > 0; });
        var html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Conta mesa ' + m.numero + '</title><style>' +
            'body{font-family:"Courier New",monospace;width:300px;margin:0 auto;padding:12px;color:#000;font-size:13px}' +
            'h1{font-size:18px;text-align:center;margin:0}p{margin:2px 0;text-align:center}hr{border:0;border-top:1px dashed #000;margin:8px 0}' +
            'table{width:100%;border-collapse:collapse}td{vertical-align:top;padding:2px 0}td.v{text-align:right;white-space:nowrap}small{color:#333}' +
            '.tot td{font-weight:bold;font-size:15px}@media print{button{display:none}}</style></head><body>' +
            '<h1>FOOD HUB</h1><p>' + (fechada ? 'CONTA FECHADA' : 'CONFERÊNCIA DE CONTA') + ' — MESA ' + m.numero + '</p><p>' + agora.toLocaleDateString('pt-BR') + ' ' + agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) + '</p>' +
            '<p>Pedidos: ' + m.pedidos.map(function (p) { return FH.esc(rotuloPedido(p)); }).join(', ') + '</p><hr>' +
            '<table>' + linhas + '</table><hr><table>' +
            '<tr><td>Total consumido</td><td class="v">' + FH.moeda(m.total_consumido) + '</td></tr>' +
            (m.ja_pago > 0 ? '<tr><td>Já pago</td><td class="v">− ' + FH.moeda(m.ja_pago) + '</td></tr>' : '') +
            '<tr class="tot"><td>' + (fechada ? 'TOTAL PAGO' : 'TOTAL A PAGAR') + '</td><td class="v">' + FH.moeda(m.saldo) + '</td></tr></table>' +
            (pags.length ? '<hr><p style="text-align:left">' + (fechada ? 'Pagamento:' : 'Formas informadas:') + '</p><table>' + pags.map(function (p) { return '<tr><td>' + FH.esc(FORMAS[p.forma] || p.forma) + '</td><td class="v">' + FH.moeda(p.valor) + '</td></tr>'; }).join('') + '</table>' : '') +
            (fechada ? '<hr><p><strong>*** PAGO ***</strong></p>' : '') + '<hr><p>Obrigado pela preferência!</p><p><small>Documento sem valor fiscal</small></p>' +
            '<p style="margin-top:10px"><button onclick="window.print()">Imprimir</button></p></body></html>';
        var w = window.open('', '_blank', 'width=380,height=640');
        if (!w) { FH.toast('O navegador bloqueou a janela de impressão. Permita pop-ups para este site.', 'erro'); return; }
        w.document.write(html); w.document.close(); w.focus();
        setTimeout(function () { w.print(); }, 250);
    }
    FH.el('btnImprimirConta').addEventListener('click', function () { if (atual) { imprimirConta(atual); } });

    FH.el('btnNovaMesa').addEventListener('click', function () { abrirMesa(null); });
    FH.el('btnAddPagto').addEventListener('click', function () { pg.adicionar('pix', Math.max(0, Math.round((total - pg.soma()) * 100) / 100)); });
    FH.el('btnFecharConta').addEventListener('click', async function () {
        var mesa = atual, pagos = pg.pagamentos();
        try {
            await FH.api('api/pedidos.php', { method: 'POST', corpo: { acao: 'fechar_conta', id_mesa: mesa.id_mesa, pagamentos: pagos } });
            FH.fecharModal('modalConta'); FH.toast('Conta da mesa ' + mesa.numero + ' fechada.', 'ok'); carregar();
        } catch (e) { FH.toast(e.message, 'erro'); return; }
        // A comanda fechada (itens + formas de pagamento) fica pronta para imprimir e entregar ao cliente
        if (await FH.confirmar('Conta fechada', 'Mesa ' + mesa.numero + ' liberada. Deseja imprimir a comanda fechada?', 'Imprimir comanda')) {
            imprimirConta(mesa, pagos);
        }
    });
    grid.addEventListener('click', async function (ev) {
        var b = ev.target.closest('button'); if (!b) { return; }
        if (b.dataset.conta) { abrirConta(achar(b.dataset.conta)); }
        if (b.dataset.ed) { abrirMesa(achar(b.dataset.ed)); }
        if (b.dataset.ex) {
            var m = achar(b.dataset.ex);
            if (!await FH.confirmar('Excluir mesa', 'Excluir a mesa ' + m.numero + '?', 'Excluir')) { return; }
            try { await FH.api('api/mesas.php', { method: 'POST', corpo: { acao: 'excluir', id_mesa: m.id_mesa } }); FH.toast('Mesa excluída.', 'ok'); carregar(); }
            catch (e) { FH.toast(e.message, 'erro'); }
        }
    });
    FH.el('formMesa').addEventListener('submit', async function (ev) {
        ev.preventDefault();
        try {
            await FH.api('api/mesas.php', { method: 'POST', corpo: { acao: 'salvar', id_mesa: Number(FH.el('mesaId').value) || 0,
                numero: Number(FH.el('mesaNumero').value), capacidade: Number(FH.el('mesaCap').value) } });
            FH.fecharModal('modalMesa'); FH.toast('Mesa salva.', 'ok'); carregar();
        } catch (e) { FH.toast(e.message, 'erro'); }
    });
    carregar();
    setInterval(carregar, 20000);  // atualiza a ocupação sozinha
})();
