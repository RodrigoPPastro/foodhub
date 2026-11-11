/* Tela de entregadores (RF21). */
(function () {
    'use strict';
    var tb = FH.el('tbEntregadores'), lista = [];
    var ROT = { disponivel: ['Disponível', 'ativo'], em_rota: ['Em rota', 'st-em-preparo'], inativo: ['Inativo', 'inativo'] };

    async function carregar() {
        try { lista = await FH.api('api/entregadores.php'); desenhar(); }
        catch (e) { tb.innerHTML = '<tr><td colspan="7" class="vazio-msg">' + FH.esc(e.message) + '</td></tr>'; }
    }
    function desenhar() {
        if (!lista.length) { tb.innerHTML = '<tr><td colspan="7" class="vazio-msg">Nenhum entregador cadastrado.</td></tr>'; return; }
        tb.innerHTML = lista.map(function (e) {
            var r = ROT[e.status] || [e.status, 'neutro'];
            return '<tr><td><strong>' + FH.esc(e.nome) + '</strong></td><td>' + FH.esc(e.telefone) + '</td><td>' + FH.esc(e.veiculo || '—') + '</td>' +
                '<td><span class="fh-badge ' + r[1] + '">' + r[0] + '</span></td><td class="num">' + e.entregas_em_andamento + '</td><td class="num">' + e.total_pedidos + '</td>' +
                '<td class="acoes-col"><button class="btn-fh-outline btn-sm" data-ed="' + e.id_entregador + '">Editar</button>' +
                '<button class="btn-fh-ghost btn-sm" data-ex="' + e.id_entregador + '">Excluir</button></td></tr>';
        }).join('');
    }
    function abrir(e) {
        FH.el('tituloEntregador').textContent = e ? 'Editar entregador' : 'Novo entregador';
        FH.el('entId').value = e ? e.id_entregador : '';
        FH.el('entNome').value = e ? e.nome : ''; FH.el('entTel').value = e ? e.telefone : '';
        FH.el('entVeiculo').value = e ? (e.veiculo || '') : '';
        FH.el('entStatus').value = e && e.status === 'inativo' ? 'inativo' : 'disponivel';
        FH.abrirModal('modalEntregador');
    }
    FH.el('btnNovo').addEventListener('click', function () { abrir(null); });
    tb.addEventListener('click', async function (ev) {
        var b = ev.target.closest('button'); if (!b) { return; }
        if (b.dataset.ed) { abrir(lista.find(function (x) { return x.id_entregador == b.dataset.ed; })); }
        if (b.dataset.ex) {
            var e = lista.find(function (x) { return x.id_entregador == b.dataset.ex; });
            if (!await FH.confirmar('Excluir entregador', 'Excluir "' + e.nome + '"?', 'Excluir')) { return; }
            try { await FH.api('api/entregadores.php', { method: 'POST', corpo: { acao: 'excluir', id_entregador: e.id_entregador } }); FH.toast('Entregador excluído.', 'ok'); carregar(); }
            catch (err) { FH.toast(err.message, 'erro'); }
        }
    });
    FH.el('formEntregador').addEventListener('submit', async function (ev) {
        ev.preventDefault();
        try {
            await FH.api('api/entregadores.php', { method: 'POST', corpo: { acao: 'salvar', id_entregador: Number(FH.el('entId').value) || 0,
                nome: FH.el('entNome').value, telefone: FH.el('entTel').value, veiculo: FH.el('entVeiculo').value, status: FH.el('entStatus').value } });
            FH.fecharModal('modalEntregador'); FH.toast('Entregador salvo.', 'ok'); carregar();
        } catch (err) { FH.toast(err.message, 'erro'); }
    });
    carregar();
})();
