/* Produtos (RF13/RF14): CRUD, foto e ficha técnica com custo/margem calculados pelo servidor. */
(function () {
    'use strict';
    var tb = FH.el('tbProdutos'), lista = [], categorias = [], insumos = [], fichaItens = [], atual = null;
    FH.abas(FH.el('blocoExtra'));

    function vNum(t) { return String(t == null ? '' : t); }
    function fmtPreco(n) { return Number(n).toFixed(2).replace('.', ','); }

    async function carregar() {
        var q = '?busca=' + encodeURIComponent(FH.el('fBusca').value) + '&id_categoria=' + (FH.el('fCat').value || 0) + '&ativo=' + FH.el('fAtivo').value;
        try { lista = await FH.api('api/produtos.php' + q); desenhar(); }
        catch (e) { tb.innerHTML = '<tr><td colspan="9" class="vazio-msg">' + FH.esc(e.message) + '</td></tr>'; }
    }
    function desenhar() {
        if (!lista.length) { tb.innerHTML = '<tr><td colspan="9" class="vazio-msg">Nenhum produto encontrado.</td></tr>'; return; }
        tb.innerHTML = lista.map(function (p) {
            var cls = p.margem_percentual >= 0 ? 'margem-pos' : 'margem-neg';
            return '<tr><td>' + (p.foto ? '<img class="fh-thumb" src="' + FH.esc(p.foto) + '" alt="">' : '<span class="fh-thumb" style="display:block"></span>') + '</td>' +
                '<td><strong>' + FH.esc(p.nome) + '</strong></td><td>' + FH.esc(p.categoria) + '</td>' +
                '<td><span class="setor-tag s-' + FH.esc(p.setor_preparo) + '">' + FH.esc(p.setor_rotulo) + '</span></td><td class="num">' + FH.moeda(p.preco_venda) + '</td>' +
                '<td class="num">' + FH.moeda(p.custo) + '</td><td class="num ' + cls + '">' + FH.percentual(p.margem_percentual) + '</td>' +
                '<td><span class="fh-badge ' + (p.ativo ? 'ativo' : 'inativo') + '">' + (p.ativo ? 'Ativo' : 'Inativo') + '</span></td>' +
                '<td class="acoes-col"><button class="btn-fh-outline btn-sm" data-ed="' + p.id_produto + '">Editar</button>' +
                '<button class="btn-fh-ghost btn-sm" data-at="' + p.id_produto + '">' + (p.ativo ? 'Inativar' : 'Ativar') + '</button>' +
                '<button class="btn-fh-ghost btn-sm" data-ex="' + p.id_produto + '">Excluir</button></td></tr>';
        }).join('');
    }

    /* ── Modal ────────────────────────────────────────────────────── */
    function mostrarFoto(url) {
        var img = FH.el('prodFotoImg'); img.hidden = !url; if (url) { img.src = url + '?t=' + Date.now(); }
        FH.el('btnRemoverFoto').hidden = !url;
    }
    async function abrir(p) {
        atual = p;
        FH.el('tituloProduto').textContent = p ? 'Editar produto' : 'Novo produto';
        FH.el('prodId').value = p ? p.id_produto : '';
        FH.el('prodNome').value = p ? p.nome : ''; FH.el('prodDesc').value = p ? (p.descricao || '') : '';
        FH.el('prodPreco').value = p ? fmtPreco(p.preco_venda) : ''; FH.el('prodAtivo').checked = p ? p.ativo : true;
        FH.el('prodSetor').value = p ? p.setor_preparo : 'cozinha';
        FH.el('prodCat').innerHTML = categorias.map(function (c) { return '<option value="' + c.id_categoria + '"' + (p && p.id_categoria === c.id_categoria ? ' selected' : '') + '>' + FH.esc(c.nome) + '</option>'; }).join('');
        FH.el('blocoExtra').hidden = !p; FH.el('dicaNovo').hidden = !!p;
        if (p) { mostrarFoto(p.foto); FH.el('prodFoto').value = ''; await carregarFicha(); }
        FH.abrirModal('modalProduto');
    }

    /* ── Ficha técnica ────────────────────────────────────────────── */
    async function carregarFicha() {
        try {
            var f = await FH.api('api/produtos.php?ficha=1&id_produto=' + atual.id_produto);
            fichaItens = f.itens.map(function (i) { return { id_insumo: i.id_insumo, nome: i.nome, un: i.unidade_medida, quantidade: i.quantidade, custo_unitario: i.custo_unitario }; });
            desenharFicha(f);
        } catch (e) { FH.toast(e.message, 'erro'); }
    }
    function desenharFicha(f) {
        FH.el('tbFicha').innerHTML = fichaItens.length ? fichaItens.map(function (i, k) {
            return '<tr><td>' + FH.esc(i.nome) + '</td><td class="num"><input class="fh-input" style="width:100px;text-align:right" data-q="' + k + '" value="' + vNum(i.quantidade).replace('.', ',') + '"></td>' +
                '<td>' + FH.esc(i.un) + '</td><td class="num">' + FH.moeda(i.quantidade * i.custo_unitario) + '</td><td><button type="button" class="btn-fh-ghost btn-sm" data-rm="' + k + '">×</button></td></tr>';
        }).join('') : '<tr><td colspan="5" class="vazio-msg">Ficha vazia — adicione os insumos do prato.</td></tr>';
        var custo = fichaItens.reduce(function (s, i) { return s + i.quantidade * i.custo_unitario; }, 0), preco = atual ? atual.preco_venda : 0;
        var marg = preco - custo, pct = preco > 0 ? marg * 100 / preco : 0;
        FH.el('fichaResumo').innerHTML = '<div><span>Preço de venda</span><strong>' + FH.moeda(preco) + '</strong></div><div><span>Custo dos insumos</span><strong>' + FH.moeda(custo) +
            '</strong></div><div class="forte"><span>Margem</span><strong class="' + (marg >= 0 ? 'margem-pos' : 'margem-neg') + '">' + FH.moeda(marg) + ' (' + FH.percentual(pct) + ')</strong></div>';
    }
    FH.el('tbFicha').addEventListener('input', function (e) {
        if (e.target.dataset.q === undefined) { return; }
        var n = parseFloat(String(e.target.value).replace(',', '.')); fichaItens[e.target.dataset.q].quantidade = isNaN(n) ? 0 : n;
    });
    FH.el('tbFicha').addEventListener('change', function () { desenharFicha(); });
    FH.el('tbFicha').addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { fichaItens.splice(Number(b.dataset.rm), 1); desenharFicha(); } });
    FH.el('btnAddInsumo').addEventListener('click', function () {
        var id = Number(FH.el('fichaInsumo').value), ins = insumos.find(function (i) { return i.id_insumo === id; });
        if (!ins) { return; }
        if (fichaItens.some(function (i) { return i.id_insumo === id; })) { FH.toast('Esse insumo já está na ficha.', 'erro'); return; }
        fichaItens.push({ id_insumo: id, nome: ins.nome, un: ins.unidade_medida, quantidade: 1, custo_unitario: ins.custo_unitario }); desenharFicha();
    });
    FH.el('btnSalvarFicha').addEventListener('click', async function () {
        try {
            var f = await FH.api('api/produtos.php', { method: 'POST', corpo: { acao: 'salvar_ficha', id_produto: atual.id_produto,
                itens: fichaItens.map(function (i) { return { id_insumo: i.id_insumo, quantidade: i.quantidade }; }) } });
            FH.toast('Ficha técnica salva. Margem: ' + FH.percentual(f.margem_percentual), 'ok'); carregar();
        } catch (e) { FH.toast(e.message, 'erro'); }
    });

    /* ── Foto ─────────────────────────────────────────────────────── */
    FH.el('btnEnviarFoto').addEventListener('click', async function () {
        var arq = FH.el('prodFoto').files[0];
        if (!arq) { FH.toast('Escolha uma imagem primeiro.', 'erro'); return; }
        var fd = new FormData(); fd.append('id_produto', atual.id_produto); fd.append('foto', arq);
        try { var r = await FH.api('api/produtos.php', { formData: fd }); atual.foto = r.foto; mostrarFoto(r.foto); FH.toast('Foto atualizada.', 'ok'); carregar(); }
        catch (e) { FH.toast(e.message, 'erro'); }
    });
    FH.el('btnRemoverFoto').addEventListener('click', async function () {
        try { await FH.api('api/produtos.php', { method: 'POST', corpo: { acao: 'remover_foto', id_produto: atual.id_produto } }); atual.foto = null; mostrarFoto(null); FH.toast('Foto removida.', 'ok'); carregar(); }
        catch (e) { FH.toast(e.message, 'erro'); }
    });

    /* ── Salvar / listar ──────────────────────────────────────────── */
    FH.el('formProduto').addEventListener('submit', async function (ev) {
        ev.preventDefault();
        try {
            var r = await FH.api('api/produtos.php', { method: 'POST', corpo: { acao: 'salvar', id_produto: Number(FH.el('prodId').value) || 0,
                nome: FH.el('prodNome').value, id_categoria: Number(FH.el('prodCat').value), preco_venda: FH.el('prodPreco').value,
                descricao: FH.el('prodDesc').value, ativo: FH.el('prodAtivo').checked, setor_preparo: FH.el('prodSetor').value } });
            FH.toast('Produto salvo.', 'ok'); await carregar();
            var novo = lista.find(function (p) { return p.id_produto === r.id_produto; });
            if (novo) { abrir(novo); }      // reabre já com foto/ficha liberadas
        } catch (e) { FH.toast(e.message, 'erro'); }
    });
    tb.addEventListener('click', async function (ev) {
        var b = ev.target.closest('button'); if (!b) { return; }
        var id = b.dataset.ed || b.dataset.at || b.dataset.ex, p = lista.find(function (x) { return x.id_produto == id; });
        if (b.dataset.ed) { abrir(p); }
        if (b.dataset.at) {
            try { await FH.api('api/produtos.php', { method: 'POST', corpo: { acao: 'ativar', id_produto: p.id_produto, ativo: !p.ativo } }); carregar(); }
            catch (e) { FH.toast(e.message, 'erro'); }
        }
        if (b.dataset.ex) {
            if (!await FH.confirmar('Excluir produto', 'Excluir "' + p.nome + '"?', 'Excluir')) { return; }
            try { await FH.api('api/produtos.php', { method: 'POST', corpo: { acao: 'excluir', id_produto: p.id_produto } }); FH.toast('Produto excluído.', 'ok'); carregar(); }
            catch (e) { FH.toast(e.message, 'erro'); }
        }
    });
    FH.el('btnNovoProd').addEventListener('click', function () { abrir(null); });
    FH.el('fBusca').addEventListener('input', FH.debounce(carregar, 300));   // RNF08: filtro por AJAX
    FH.el('fCat').addEventListener('change', carregar); FH.el('fAtivo').addEventListener('change', carregar);

    (async function () {
        try {
            categorias = await FH.api('api/categorias.php');
            var d = await FH.api('api/insumos.php'); insumos = d.insumos;
            FH.el('fCat').insertAdjacentHTML('beforeend', categorias.map(function (c) { return '<option value="' + c.id_categoria + '">' + FH.esc(c.nome) + '</option>'; }).join(''));
            FH.el('fichaInsumo').innerHTML = insumos.map(function (i) { return '<option value="' + i.id_insumo + '">' + FH.esc(i.nome) + ' (' + FH.esc(i.unidade_medida) + ')</option>'; }).join('');
        } catch (e) { FH.toast(e.message, 'erro'); }
        carregar();
    })();
})();
