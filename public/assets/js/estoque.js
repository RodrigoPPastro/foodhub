/* Estoque (RF15/RF16): insumos, movimentações e alerta de nível mínimo. */
(function () {
    'use strict';
    FH.abas(FH.el('abasEst').parentNode);
    var tb = FH.el('tbInsumos'), lista = [], unidades = {};
    var TIPO = { entrada: ['Entrada', 'ativo'], saida: ['Saída', 'st-em-preparo'], perda: ['Perda', 'inativo'] };

    async function carregar() {
        var q = '?busca=' + encodeURIComponent(FH.el('eBusca').value) + (FH.el('eBaixo').checked ? '&baixo=1' : '');
        try {
            var d = await FH.api('api/insumos.php' + q);
            lista = d.insumos; unidades = d.unidades; desenhar(d.total_baixo);
            if (!FH.el('insUn').options.length) {
                FH.el('insUn').innerHTML = Object.keys(unidades).map(function (u) { return '<option value="' + u + '">' + FH.esc(unidades[u]) + '</option>'; }).join('');
            }
        } catch (e) { tb.innerHTML = '<tr><td colspan="7" class="vazio-msg">' + FH.esc(e.message) + '</td></tr>'; }
    }
    function desenhar(baixos) {
        FH.el('alertaEstoque').innerHTML = baixos ? '<div class="fh-alert erro" role="alert"><strong>Atenção:</strong> ' + baixos + ' insumo(s) abaixo do estoque mínimo. Reponha para não travar a cozinha.</div>' : '';
        if (!lista.length) { tb.innerHTML = '<tr><td colspan="7" class="vazio-msg">Nenhum insumo encontrado.</td></tr>'; return; }
        tb.innerHTML = lista.map(function (i) {
            var pct = i.estoque_minimo > 0 ? Math.min(100, i.estoque_atual * 100 / (i.estoque_minimo * 2)) : 100;
            return '<tr class="' + (i.abaixo_minimo ? 'linha-alerta' : '') + '"><td><strong>' + FH.esc(i.nome) + '</strong>' + (i.abaixo_minimo ? ' <span class="fh-badge alerta">Baixo</span>' : '') + '</td>' +
                '<td class="num">' + FH.numero(i.estoque_atual) + ' ' + FH.esc(i.unidade_medida) + '</td><td class="num">' + FH.numero(i.estoque_minimo) + ' ' + FH.esc(i.unidade_medida) + '</td>' +
                '<td><div class="nivel ' + (i.abaixo_minimo ? 'baixo' : '') + '"><i style="width:' + pct + '%"></i></div></td>' +
                '<td class="num">' + FH.moeda(i.custo_unitario) + '</td><td class="num">' + FH.moeda(i.valor_em_estoque) + '</td>' +
                '<td class="acoes-col"><button class="btn-fh btn-sm" data-mov="' + i.id_insumo + '">Movimentar</button>' +
                '<button class="btn-fh-outline btn-sm" data-ed="' + i.id_insumo + '">Editar</button>' +
                '<button class="btn-fh-ghost btn-sm" data-ex="' + i.id_insumo + '">Excluir</button></td></tr>';
        }).join('');
    }
    async function carregarMov() {
        var t = FH.el('tbMov');
        try {
            var l = await FH.api('api/insumos.php?movimentos=1&limite=100');
            t.innerHTML = l.length ? l.map(function (m) {
                var r = TIPO[m.tipo] || [m.tipo, 'neutro'];
                return '<tr><td class="nowrap">' + FH.esc(m.data_hora) + '</td><td>' + FH.esc(m.insumo) + '</td><td><span class="fh-badge ' + r[1] + '">' + r[0] + '</span></td>' +
                    '<td class="num">' + FH.numero(m.quantidade) + ' ' + FH.esc(m.unidade_medida) + '</td><td>' + FH.esc(m.motivo || '—') + '</td><td>' + FH.esc(m.usuario) + '</td></tr>';
            }).join('') : '<tr><td colspan="6" class="vazio-msg">Sem movimentações.</td></tr>';
        } catch (e) { t.innerHTML = '<tr><td colspan="6" class="vazio-msg">' + FH.esc(e.message) + '</td></tr>'; }
    }
    function achar(id) { return lista.find(function (i) { return i.id_insumo == id; }); }

    function abrirInsumo(i) {
        FH.el('tituloInsumo').textContent = i ? 'Editar insumo' : 'Novo insumo';
        FH.el('insId').value = i ? i.id_insumo : ''; FH.el('insNome').value = i ? i.nome : '';
        FH.el('insUn').value = i ? i.unidade_medida : 'kg'; FH.el('insMin').value = i ? String(i.estoque_minimo).replace('.', ',') : '0';
        FH.el('insCusto').value = i ? String(i.custo_unitario).replace('.', ',') : '0'; FH.el('insInicial').value = '0';
        FH.el('grpInicial').hidden = !!i;     // saldo só muda por movimentação (rastreável)
        FH.abrirModal('modalInsumo');
    }
    FH.el('btnNovoInsumo').addEventListener('click', function () { abrirInsumo(null); });
    tb.addEventListener('click', async function (ev) {
        var b = ev.target.closest('button'); if (!b) { return; }
        if (b.dataset.ed) { abrirInsumo(achar(b.dataset.ed)); }
        if (b.dataset.mov) {
            var i = achar(b.dataset.mov);
            FH.el('tituloMov').textContent = 'Movimentar: ' + i.nome; FH.el('movId').value = i.id_insumo; FH.el('movQtd').value = ''; FH.el('movMotivo').value = '';
            FH.el('movGerarConta').checked = false; FH.el('boxConta').hidden = true; FH.el('movFornecedor').value = ''; FH.el('movValor').value = '';
            FH.el('movVenc').value = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10); FH.el('movTipo').value = 'entrada'; ajustarCompra();
            FH.abrirModal('modalMov');
        }
        if (b.dataset.ex) {
            var x = achar(b.dataset.ex);
            if (!await FH.confirmar('Excluir insumo', 'Excluir "' + x.nome + '"? Não é possível se ele estiver em alguma ficha técnica.', 'Excluir')) { return; }
            try { await FH.api('api/insumos.php', { method: 'POST', corpo: { acao: 'excluir', id_insumo: x.id_insumo } }); FH.toast('Insumo excluído.', 'ok'); carregar(); }
            catch (e) { FH.toast(e.message, 'erro'); }
        }
    });
    FH.el('formInsumo').addEventListener('submit', async function (ev) {
        ev.preventDefault();
        try {
            await FH.api('api/insumos.php', { method: 'POST', corpo: { acao: 'salvar', id_insumo: Number(FH.el('insId').value) || 0, nome: FH.el('insNome').value,
                unidade_medida: FH.el('insUn').value, estoque_minimo: FH.el('insMin').value, custo_unitario: FH.el('insCusto').value, estoque_atual: FH.el('insInicial').value } });
            FH.fecharModal('modalInsumo'); FH.toast('Insumo salvo.', 'ok'); carregar(); carregarMov();
        } catch (e) { FH.toast(e.message, 'erro'); }
    });
    /* Conta a pagar só faz sentido para ENTRADA (compra). */
    function ajustarCompra() { FH.el('boxCompra').hidden = FH.el('movTipo').value !== 'entrada'; }
    FH.el('movTipo').addEventListener('change', ajustarCompra);
    FH.el('movGerarConta').addEventListener('change', function (e) { FH.el('boxConta').hidden = !e.target.checked; });
    FH.el('formMov').addEventListener('submit', async function (ev) {
        ev.preventDefault();
        try {
            var corpo = { acao: 'movimentar', id_insumo: Number(FH.el('movId').value), tipo: FH.el('movTipo').value, quantidade: FH.el('movQtd').value, motivo: FH.el('movMotivo').value };
            if (corpo.tipo === 'entrada' && FH.el('movGerarConta').checked) {
                corpo.gerar_conta = { ativo: true, pessoa: FH.el('movFornecedor').value, valor: FH.el('movValor').value, data_vencimento: FH.el('movVenc').value };
            }
            var r = await FH.api('api/insumos.php', { method: 'POST', corpo: corpo });
            FH.fecharModal('modalMov'); FH.toast(r.id_conta ? 'Movimentação registrada e conta a pagar criada.' : 'Movimentação registrada.', 'ok'); carregar(); carregarMov();
        } catch (e) { FH.toast(e.message, 'erro'); }
    });
    FH.el('eBusca').addEventListener('input', FH.debounce(carregar, 300)); FH.el('eBaixo').addEventListener('change', carregar);
    carregar(); carregarMov();
})();
