/* Usuários: lista de funcionários e grade de permissões (ver / incluir / alterar / excluir por tela).
 * O servidor valida tudo de novo (api/usuarios.php → PermissaoService). */
(function () {
    'use strict';
    var ACOES = ['ver', 'incluir', 'alterar', 'excluir'];
    var catalogo = null, atual = null;

    async function carregarCatalogo() {
        if (!catalogo) { catalogo = await FH.api('api/usuarios.php?visao=catalogo'); }
        return catalogo;
    }

    /* ── Lista de funcionários ───────────────────────────────────── */
    async function carregarFuncionarios() {
        var tb = FH.el('tbFuncionarios');
        try {
            var l = await FH.api('api/usuarios.php?visao=funcionarios');
            tb.innerHTML = l.length ? l.map(function (f) {
                return '<tr><td><strong>' + FH.esc(f.nome_completo) + '</strong></td><td><code>' + FH.esc(f.login) + '</code></td>' +
                    '<td>' + (f.telas.length ? f.telas.map(function (t) { return '<span class="fh-badge neutro" style="margin:1px">' + FH.esc(t) + '</span>'; }).join(' ')
                                             : '<span class="fh-badge alerta">Nenhuma tela liberada</span>') + '</td>' +
                    '<td style="text-align:center;white-space:nowrap"><button type="button" class="btn-fh-outline btn-sm" data-permissoes="' + f.id_usuario + '">Permissões</button> ' +
                    '<button type="button" class="btn-fh btn-sm" style="background:var(--red)" data-excluir-func="' + f.id_usuario + '" data-nome="' + FH.esc(f.nome_completo) + '">Excluir</button></td></tr>';
            }).join('') : '<tr><td colspan="4" class="vazio-msg">Nenhum funcionário cadastrado. Clique em "+ Novo funcionário".</td></tr>';
        } catch (e) { tb.innerHTML = '<tr><td colspan="4" class="vazio-msg">' + FH.esc(e.message) + '</td></tr>'; }
    }

    /* ── Grade de permissões ─────────────────────────────────────── */
    function desenharGrade(perm) {
        var grupo = '';
        FH.el('tbPerm').innerHTML = catalogo.modulos.map(function (m) {
            var cab = '';
            if (m.grupo !== grupo) { grupo = m.grupo; cab = '<tr class="grupo"><td colspan="5">' + FH.esc(grupo) + '</td></tr>'; }
            var p = perm[m.codigo] || {};
            return cab + '<tr><td>' + FH.esc(m.rotulo) + '</td>' + ACOES.map(function (a) {
                if (m.acoes.indexOf(a) < 0) { return '<td class="na">—</td>'; }
                var dica = m.dicas && m.dicas[a] ? m.dicas[a] : '';
                return '<td><label class="chk-perm" title="' + FH.esc(dica) + '"><input type="checkbox" data-mod="' + m.codigo + '" data-acao="' + a + '"' + (p[a] ? ' checked' : '') + '>' +
                    (dica ? '<small>' + FH.esc(dica) + '</small>' : '') + '</label></td>';
            }).join('') + '</tr>';
        }).join('');
    }
    function lerGrade() {
        var perm = {};
        FH.el('tbPerm').querySelectorAll('input[data-mod]:checked').forEach(function (c) {
            (perm[c.dataset.mod] = perm[c.dataset.mod] || []).push(c.dataset.acao);
        });
        return perm;
    }
    function aplicarModelo(codigo) {
        var mod = catalogo.modelos.find(function (x) { return x.codigo === codigo; });
        var perm = {};
        Object.keys(mod.permissoes).forEach(function (m) { perm[m] = {}; mod.permissoes[m].forEach(function (a) { perm[m][a] = true; }); });
        desenharGrade(perm);
        FH.toast('Modelo "' + mod.rotulo + '" aplicado. Ajuste se precisar e clique em Salvar.', 'ok');
    }
    function ajustarPerfil() { FH.el('blocoPerm').hidden = FH.el('permPerfil').value !== 'funcionario'; FH.el('permLimpar').hidden = FH.el('blocoPerm').hidden; }

    async function abrir(idUsuario) {
        try {
            await carregarCatalogo();
            atual = await FH.api('api/usuarios.php?id_usuario=' + idUsuario);
        } catch (e) { FH.toast(e.message, 'erro'); return; }
        FH.el('tituloPerm').textContent = 'Permissões — ' + atual.nome + ' (' + atual.login + ')';
        FH.el('permPerfil').value = atual.perfil === 'funcionario' ? 'funcionario' : 'comum';
        FH.el('permModelos').innerHTML = catalogo.modelos.map(function (m) {
            return '<button type="button" class="btn-fh-outline btn-sm" data-modelo="' + m.codigo + '">' + FH.esc(m.rotulo) + '</button>';
        }).join('');
        desenharGrade(atual.permissoes);
        ajustarPerfil();
        FH.abrirModal('modalPermissoes');
    }

    FH.el('tbPerm').addEventListener('change', function (ev) {
        var c = ev.target; if (!c.dataset || !c.dataset.mod) { return; }
        var linha = FH.el('tbPerm').querySelectorAll('input[data-mod="' + c.dataset.mod + '"]');
        if (c.dataset.acao !== 'ver' && c.checked) {          // incluir/alterar/excluir exigem ver
            linha.forEach(function (x) { if (x.dataset.acao === 'ver') { x.checked = true; } });
        }
        if (c.dataset.acao === 'ver' && !c.checked) {         // sem ver, nada mais
            linha.forEach(function (x) { x.checked = false; });
        }
    });
    FH.el('permModelos').addEventListener('click', function (ev) { var b = ev.target.closest('[data-modelo]'); if (b) { aplicarModelo(b.dataset.modelo); } });
    FH.el('permPerfil').addEventListener('change', ajustarPerfil);
    FH.el('permLimpar').addEventListener('click', function () { desenharGrade({}); });
    FH.el('permSalvar').addEventListener('click', async function () {
        var perfil = FH.el('permPerfil').value, perm = perfil === 'funcionario' ? lerGrade() : {};
        if (perfil === 'funcionario' && !Object.keys(perm).length &&
            !await FH.confirmar('Nenhuma tela marcada', 'Este funcionário não vai conseguir acessar nenhuma tela. Salvar mesmo assim?', 'Salvar')) { return; }
        try {
            await FH.api('api/usuarios.php', { method: 'POST', corpo: { acao: 'permissoes', id_usuario: atual.id_usuario, perfil: perfil, permissoes: perm } });
            FH.fecharModal('modalPermissoes');
            FH.toast('Permissões de ' + atual.nome + ' salvas. Valem a partir da próxima tela que ele abrir.', 'ok');
            carregarFuncionarios();
            if (document.querySelector('[data-permissoes="' + atual.id_usuario + '"]') && location.search.indexOf('busca=') >= 0) {
                setTimeout(function () { location.reload(); }, 900);   // atualiza a coluna "Perfil" da busca
            }
        } catch (e) { FH.toast(e.message, 'erro'); }
    });

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-permissoes]');
        if (b) { abrir(Number(b.dataset.permissoes)); return; }
        var x = ev.target.closest('[data-excluir-func]');
        if (x && typeof window.abrirModalExcluir === 'function') { window.abrirModalExcluir(Number(x.dataset.excluirFunc), x.dataset.nome); }
    });

    carregarFuncionarios();
    // Recém-cadastrado pelo "+ Novo funcionário": já abre a grade de permissões
    var m = location.search.match(/[?&]permissoes=(\d+)/);
    if (m) { abrir(Number(m[1])); }
})();
