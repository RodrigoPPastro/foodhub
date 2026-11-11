/* Tela de categorias (RF13). Lista, cria, edita e exclui via api/categorias.php. */
(function () {
    'use strict';
    var tb = FH.el('tbCategorias'), lista = [];

    async function carregar() {
        try { lista = await FH.api('api/categorias.php'); desenhar(); }
        catch (e) { tb.innerHTML = '<tr><td colspan="4" class="vazio-msg">' + FH.esc(e.message) + '</td></tr>'; }
    }

    function desenhar() {
        if (!lista.length) { tb.innerHTML = '<tr><td colspan="4" class="vazio-msg">Nenhuma categoria cadastrada.</td></tr>'; return; }
        tb.innerHTML = lista.map(function (c) {
            return '<tr><td><strong>' + FH.esc(c.nome) + '</strong></td><td>' + FH.esc(c.descricao || '—') + '</td>' +
                '<td class="num">' + c.total_produtos + '</td><td class="acoes-col">' +
                '<button class="btn-fh-outline btn-sm" data-ed="' + c.id_categoria + '">Editar</button>' +
                '<button class="btn-fh-ghost btn-sm" data-ex="' + c.id_categoria + '">Excluir</button></td></tr>';
        }).join('');
    }

    function abrir(c) {
        FH.el('tituloCategoria').textContent = c ? 'Editar categoria' : 'Nova categoria';
        FH.el('catId').value = c ? c.id_categoria : '';
        FH.el('catNome').value = c ? c.nome : '';
        FH.el('catDesc').value = c ? (c.descricao || '') : '';
        FH.abrirModal('modalCategoria');
    }

    FH.el('btnNova').addEventListener('click', function () { abrir(null); });
    tb.addEventListener('click', async function (e) {
        var b = e.target.closest('button'); if (!b) { return; }
        if (b.dataset.ed) { abrir(lista.find(function (c) { return c.id_categoria == b.dataset.ed; })); }
        if (b.dataset.ex) {
            var c = lista.find(function (x) { return x.id_categoria == b.dataset.ex; });
            if (!await FH.confirmar('Excluir categoria', 'Excluir "' + c.nome + '"? Só é possível se não houver produtos nela.', 'Excluir')) { return; }
            try { await FH.api('api/categorias.php', { method: 'POST', corpo: { acao: 'excluir', id_categoria: c.id_categoria } }); FH.toast('Categoria excluída.', 'ok'); carregar(); }
            catch (err) { FH.toast(err.message, 'erro'); }
        }
    });
    FH.el('formCategoria').addEventListener('submit', async function (e) {
        e.preventDefault();
        try {
            await FH.api('api/categorias.php', { method: 'POST', corpo: {
                acao: 'salvar', id_categoria: Number(FH.el('catId').value) || 0,
                nome: FH.el('catNome').value, descricao: FH.el('catDesc').value } });
            FH.fecharModal('modalCategoria'); FH.toast('Categoria salva.', 'ok'); carregar();
        } catch (err) { FH.toast(err.message, 'erro'); }
    });
    carregar();
})();
