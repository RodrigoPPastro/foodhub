/* Contas a pagar / a receber (Financeiro). Toda regra é validada no servidor (api/contas.php). */
(function () {
    'use strict';
    var tipo = 'pagar', contas = [], categorias = {}, canais = [], selecionadas = {};
    var CAT_TODAS = {
        pagar: { insumos: 'Insumos / fornecedores', aluguel: 'Aluguel', energia: 'Energia', agua: 'Água', internet: 'Internet / telefone', folha: 'Folha / salários', entregadores: 'Entregadores', impostos: 'Impostos e taxas', manutencao: 'Manutenção', outros: 'Outros' },
        receber: { repasse_plataforma: 'Repasse de plataforma', venda_prazo: 'Venda a prazo', outros: 'Outros' }
    };
    var SIT = { aberta: ['Em aberto', 'st-novo'], vencida: ['Vencida', 'alerta'], baixada: ['Baixada', 'ativo'], cancelada: ['Cancelada', 'neutro'] };

    function hoje() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function api(qs) { return FH.api('api/contas.php?' + qs); }
    function post(corpo) { return FH.api('api/contas.php', { method: 'POST', corpo: corpo }); }
    function num(v) { return String(v).replace('.', ','); }

    /* ── Fluxo de caixa ───────────────────────────────────────────── */
    async function carregarFluxo() {
        try {
            var f = await api('visao=fluxo&de=' + FH.el('fxDe').value + '&ate=' + FH.el('fxAte').value);
            FH.el('fxDe').value = f.periodo.de; FH.el('fxAte').value = f.periodo.ate;
            FH.el('fxEntradas').textContent = FH.moeda(f.entradas_vendas_diretas + f.entradas_recebimentos);
            FH.el('fxEntradasSub').textContent = FH.moeda(f.entradas_vendas_diretas) + ' vendas · ' + FH.moeda(f.entradas_recebimentos) + ' recebimentos';
            FH.el('fxSaidas').textContent = FH.moeda(f.saidas_pagas);
            FH.el('fxSaldo').textContent = FH.moeda(f.saldo);
            FH.el('fxSaldoCard').classList.toggle('alerta', f.saldo < 0);
            FH.el('fxProj').textContent = FH.moeda(f.projecao);
            FH.el('fxProjSub').textContent = '+ ' + FH.moeda(f.a_receber) + ' a receber − ' + FH.moeda(f.a_pagar) + ' a pagar';
        } catch (e) { FH.toast(e.message, 'erro'); }
    }

    /* ── Lista ────────────────────────────────────────────────────── */
    async function carregar() {
        var qs = 'tipo=' + tipo + '&situacao=' + FH.el('fSit').value + '&de=' + FH.el('fDe').value + '&ate=' + FH.el('fAte').value + '&busca=' + encodeURIComponent(FH.el('fBusca').value);
        try {
            var d = await api(qs);
            contas = d.contas; categorias = d.categorias; selecionadas = {};
            FH.el('resAberto').textContent = FH.moeda(d.resumo.em_aberto);
            FH.el('resVencido').textContent = FH.moeda(d.resumo.vencido);
            FH.el('resQtdVenc').textContent = d.resumo.qtd_vencidas ? d.resumo.qtd_vencidas + ' conta(s) vencida(s)' : 'nenhuma vencida';
            FH.el('cardVencido').classList.toggle('alerta', d.resumo.qtd_vencidas > 0);
            FH.el('resLista').textContent = FH.moeda(d.resumo.soma_listada);
            desenhar();
        } catch (e) { FH.el('tbContas').innerHTML = '<tr><td colspan="7" class="vazio-msg">' + FH.esc(e.message) + '</td></tr>'; }
    }
    function desenhar() {
        var tb = FH.el('tbContas');
        if (!contas.length) { tb.innerHTML = '<tr><td colspan="7" class="vazio-msg">Nenhuma conta encontrada.</td></tr>'; atualizarLote(); return; }
        tb.innerHTML = contas.map(function (c) {
            var s = SIT[c.situacao] || [c.situacao, 'neutro'], aberta = c.status === 'aberta';
            var acoes = '';
            if (aberta) {
                acoes += '<button class="btn-fh btn-sm" data-baixar="' + c.id_conta + '">' + (tipo === 'pagar' ? 'Pagar' : 'Receber') + '</button>';
                if (!c.automatica) { acoes += '<button class="btn-fh-outline btn-sm" data-ed="' + c.id_conta + '">Editar</button>'; }
                acoes += '<button class="btn-fh-ghost btn-sm" data-cancelar="' + c.id_conta + '">Cancelar</button>';
            } else if (c.status === 'baixada') {
                acoes += '<button class="btn-fh-ghost btn-sm" data-estornar="' + c.id_conta + '">Estornar</button>';
            }
            var det = c.automatica && c.valor_bruto != null ? '<br><small class="muted">Pedido #' + c.id_pedido + ' · bruto ' + FH.moeda(c.valor_bruto) + ' − ' + FH.esc(num(c.comissao_percentual)) + '%</small>' : (c.pessoa ? '<br><small class="muted">' + FH.esc(c.pessoa) + '</small>' : '');
            return '<tr class="' + (c.situacao === 'vencida' ? 'linha-alerta' : '') + '"><td>' + (aberta ? '<input type="checkbox" data-sel="' + c.id_conta + '" aria-label="Selecionar conta ' + c.id_conta + '">' : '') + '</td>' +
                '<td class="nowrap">' + FH.dataBr(c.data_vencimento) + (c.data_baixa ? '<br><small class="muted">baixa ' + FH.dataBr(c.data_baixa) + '</small>' : '') + '</td>' +
                '<td><strong>' + FH.esc(c.descricao) + '</strong>' + det + (c.observacao && !c.automatica ? '<br><small class="muted">' + FH.esc(c.observacao) + '</small>' : '') + '</td>' +
                '<td>' + FH.esc(c.categoria_rotulo) + '</td><td class="num">' + FH.moeda(c.valor) + '</td>' +
                '<td><span class="fh-badge ' + s[1] + '">' + s[0] + (c.dias_atraso ? ' · ' + c.dias_atraso + 'd' : '') + '</span></td><td class="acoes-col">' + acoes + '</td></tr>';
        }).join('');
        atualizarLote();
    }
    function atualizarLote() {
        var n = Object.keys(selecionadas).length;
        FH.el('btnLote').hidden = n === 0;
        FH.el('btnLote').textContent = (tipo === 'pagar' ? 'Pagar' : 'Receber') + ' selecionadas (' + n + ')';
    }
    function achar(id) { return contas.find(function (c) { return c.id_conta == id; }); }

    /* ── Abas ─────────────────────────────────────────────────────── */
    FH.el('abasTipo').addEventListener('click', function (e) {
        var b = e.target.closest('[data-tipo]'); if (!b) { return; }
        tipo = b.dataset.tipo;
        FH.el('abasTipo').querySelectorAll('.fh-tab').forEach(function (x) { x.classList.toggle('ativa', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
        FH.el('painelCanais').hidden = tipo !== 'canais'; FH.el('painelLista').hidden = tipo === 'canais';
        if (tipo === 'canais') { carregarCanais(); return; }
        FH.el('optBaixada').textContent = tipo === 'pagar' ? 'Pagas' : 'Recebidas';
        FH.el('resRotuloAberto').textContent = tipo === 'pagar' ? 'A pagar (em aberto)' : 'A receber (em aberto)';
        carregar();
    });
    ['fSit', 'fDe', 'fAte'].forEach(function (i) { FH.el(i).addEventListener('change', carregar); });
    FH.el('fBusca').addEventListener('input', FH.debounce(carregar, 300));      // RNF08: filtro por AJAX
    FH.el('formFluxo').addEventListener('submit', function (e) { e.preventDefault(); carregarFluxo(); });

    /* ── Ações da tabela ──────────────────────────────────────────── */
    FH.el('tbContas').addEventListener('change', function (e) {
        var s = e.target.dataset.sel; if (!s) { return; }
        if (e.target.checked) { selecionadas[s] = 1; } else { delete selecionadas[s]; }
        atualizarLote();
    });
    FH.el('chkTodas').addEventListener('change', function (e) {
        selecionadas = {};
        FH.el('tbContas').querySelectorAll('[data-sel]').forEach(function (c) { c.checked = e.target.checked; if (e.target.checked) { selecionadas[c.dataset.sel] = 1; } });
        atualizarLote();
    });
    FH.el('tbContas').addEventListener('click', async function (ev) {
        var b = ev.target.closest('button'); if (!b) { return; }
        if (b.dataset.baixar) { abrirBaixa(achar(b.dataset.baixar)); }
        if (b.dataset.ed) { abrirConta(achar(b.dataset.ed)); }
        if (b.dataset.cancelar) {
            var c = achar(b.dataset.cancelar);
            if (!await FH.confirmar('Cancelar conta', 'Cancelar "' + c.descricao + '"? Ela deixa de contar nos totais.', 'Cancelar conta')) { return; }
            try { await post({ acao: 'cancelar', id_conta: c.id_conta }); FH.toast('Conta cancelada.', 'ok'); carregar(); carregarFluxo(); } catch (e) { FH.toast(e.message, 'erro'); }
        }
        if (b.dataset.estornar) {
            var x = achar(b.dataset.estornar);
            if (!await FH.confirmar('Estornar baixa', 'Reabrir "' + x.descricao + '"? Ela volta para "em aberto".', 'Estornar')) { return; }
            try { await post({ acao: 'estornar', id_conta: x.id_conta }); FH.toast('Baixa estornada.', 'ok'); carregar(); carregarFluxo(); } catch (e) { FH.toast(e.message, 'erro'); }
        }
    });
    FH.el('btnLote').addEventListener('click', async function () {
        var ids = Object.keys(selecionadas).map(Number);
        if (!await FH.confirmar('Baixar em lote', 'Baixar ' + ids.length + ' conta(s) com a data de hoje e o valor previsto?', 'Baixar')) { return; }
        try { var r = await post({ acao: 'baixar_lote', ids: ids }); FH.toast(r.baixadas + ' conta(s) baixada(s).', 'ok'); carregar(); carregarFluxo(); }
        catch (e) { FH.toast(e.message, 'erro'); }
    });

    /* ── Modal baixa ──────────────────────────────────────────────── */
    function abrirBaixa(c) {
        FH.el('tituloBaixa').textContent = tipo === 'pagar' ? 'Registrar pagamento' : 'Registrar recebimento';
        FH.el('baixaDesc').textContent = c.descricao + ' — previsto ' + FH.moeda(c.valor);
        FH.el('bxId').value = c.id_conta; FH.el('bxData').value = hoje(); FH.el('bxData').max = hoje(); FH.el('bxValor').value = Number(c.valor).toFixed(2).replace('.', ',');
        FH.abrirModal('modalBaixa');
    }
    FH.el('formBaixa').addEventListener('submit', async function (e) {
        e.preventDefault();
        try {
            await post({ acao: 'baixar', id_conta: Number(FH.el('bxId').value), data_baixa: FH.el('bxData').value, valor: FH.el('bxValor').value });
            FH.fecharModal('modalBaixa'); FH.toast('Baixa registrada.', 'ok'); carregar(); carregarFluxo();
        } catch (err) { FH.toast(err.message, 'erro'); }
    });

    /* ── Modal conta ──────────────────────────────────────────────── */
    function preencherCategorias(t, sel) {
        FH.el('ctCat').innerHTML = Object.keys(CAT_TODAS[t]).map(function (k) { return '<option value="' + k + '"' + (k === sel ? ' selected' : '') + '>' + FH.esc(CAT_TODAS[t][k]) + '</option>'; }).join('');
        FH.el('lblPessoa').textContent = t === 'pagar' ? 'Fornecedor' : 'Cliente / origem';
    }
    function abrirConta(c) {
        FH.el('tituloConta').textContent = c ? 'Editar conta' : 'Nova conta';
        FH.el('ctId').value = c ? c.id_conta : '';
        FH.el('ctTipo').value = c ? c.tipo : tipo; FH.el('ctTipo').disabled = !!c;
        preencherCategorias(FH.el('ctTipo').value, c ? c.categoria : null);
        FH.el('ctDesc').value = c ? c.descricao : ''; FH.el('ctPessoa').value = c ? (c.pessoa || '') : '';
        FH.el('ctValor').value = c ? Number(c.valor).toFixed(2).replace('.', ',') : ''; FH.el('ctVenc').value = c ? c.data_vencimento : hoje();
        FH.el('ctObs').value = c ? (c.observacao || '') : '';
        FH.abrirModal('modalConta');
    }
    FH.el('ctTipo').addEventListener('change', function () { preencherCategorias(FH.el('ctTipo').value, null); });
    FH.el('btnNovaConta').addEventListener('click', function () { if (tipo === 'canais') { tipo = 'pagar'; } abrirConta(null); });
    FH.el('formConta').addEventListener('submit', async function (e) {
        e.preventDefault();
        try {
            var r = await post({ acao: 'salvar', id_conta: Number(FH.el('ctId').value) || 0, tipo: FH.el('ctTipo').value, categoria: FH.el('ctCat').value, descricao: FH.el('ctDesc').value,
                pessoa: FH.el('ctPessoa').value, valor: FH.el('ctValor').value, data_vencimento: FH.el('ctVenc').value, observacao: FH.el('ctObs').value });
            FH.fecharModal('modalConta'); FH.toast('Conta salva.', 'ok');
            if (r.tipo !== tipo && tipo !== 'canais') { document.querySelector('[data-tipo="' + r.tipo + '"]').click(); } else { carregar(); }
            carregarFluxo();
        } catch (err) { FH.toast(err.message, 'erro'); }
    });

    /* ── Comissão das plataformas ─────────────────────────────────── */
    async function carregarCanais() {
        try {
            canais = await api('visao=canais');
            FH.el('tbCanais').innerHTML = canais.map(function (c) {
                return '<tr><td><strong>' + FH.esc(c.rotulo) + '</strong></td>' +
                    '<td class="num"><input class="fh-input" style="text-align:right" data-com="' + FH.esc(c.codigo) + '" value="' + num(c.comissao_percentual) + '" aria-label="Comissão ' + FH.esc(c.rotulo) + '"></td>' +
                    '<td class="num"><input class="fh-input" style="text-align:right" type="number" min="0" max="365" data-prazo="' + FH.esc(c.codigo) + '" value="' + c.prazo_repasse_dias + '" aria-label="Prazo ' + FH.esc(c.rotulo) + '"></td>' +
                    '<td class="acoes-col"><button class="btn-fh btn-sm" data-salvar-canal="' + FH.esc(c.codigo) + '">Salvar</button></td></tr>';
            }).join('') || '<tr><td colspan="4" class="vazio-msg">Nenhuma plataforma cadastrada.</td></tr>';
        } catch (e) { FH.toast(e.message, 'erro'); }
    }
    FH.el('tbCanais').addEventListener('click', async function (ev) {
        var b = ev.target.closest('[data-salvar-canal]'); if (!b) { return; }
        var cod = b.dataset.salvarCanal, tr = b.closest('tr');
        try {
            await post({ acao: 'salvar_canal', codigo: cod, comissao_percentual: tr.querySelector('[data-com]').value, prazo_repasse_dias: Number(tr.querySelector('[data-prazo]').value) });
            FH.toast('Comissão e prazo atualizados.', 'ok');
        } catch (e) { FH.toast(e.message, 'erro'); }
    });

    (async function () { await carregarFluxo(); carregar(); })();
})();
