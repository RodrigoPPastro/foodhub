/* Janela de avisos do Master, aberta uma vez logo após o login.
 * Mostra as pendências (estoque e contas) e lê um resumo em voz alta (Web Speech API, voz pt-BR). */
(function () {
    'use strict';
    var CHAVE_VOZ = 'fh_avisos_voz';   // 'off' = silenciado (lembrado neste navegador)
    var dados = null, falou = false;

    function vozLigada() { try { return localStorage.getItem(CHAVE_VOZ) !== 'off'; } catch (e) { return true; } }
    function definirVoz(ligada) { try { localStorage.setItem(CHAVE_VOZ, ligada ? 'on' : 'off'); } catch (e) { /* sem storage */ } }

    function vozPtBr() {
        var vozes = window.speechSynthesis ? speechSynthesis.getVoices() : [];
        return vozes.find(function (v) { return /pt[-_]BR/i.test(v.lang); }) || vozes.find(function (v) { return /^pt/i.test(v.lang); }) || null;
    }

    /** Fala o resumo. Se o navegador bloquear (falta de clique na página), avisa e fala no primeiro clique. */
    function falar(forcar) {
        if (!dados || !('speechSynthesis' in window) || (!vozLigada() && !forcar)) { return; }
        speechSynthesis.cancel();
        var u = new SpeechSynthesisUtterance(dados.fala);
        u.lang = 'pt-BR'; u.rate = 1; u.pitch = 1;
        var v = vozPtBr(); if (v) { u.voice = v; }
        var comecou = false;
        u.onstart = function () { comecou = true; falou = true; FH.el('avisoDicaVoz').hidden = true; };
        speechSynthesis.speak(u);
        setTimeout(function () {
            if (!comecou && !falou) { FH.el('avisoDicaVoz').hidden = false; }   // bloqueado até o primeiro clique
        }, 1200);
    }

    function linhaItem(g, i) {
        if (g.chave.indexOf('estoque') === 0) {
            return '<li><strong>' + FH.esc(i.nome) + '</strong> — ' + FH.numero(i.atual) + ' ' + FH.esc(i.unidade) +
                ' <span class="muted">(mínimo ' + FH.numero(i.minimo) + ' ' + FH.esc(i.unidade) + ')</span></li>';
        }
        var extra = i.dias_atraso > 0 ? ' <span class="aviso-atraso">' + i.dias_atraso + (i.dias_atraso === 1 ? ' dia' : ' dias') + ' de atraso</span>'
                                      : ' <span class="muted">vence ' + FH.dataBr(i.vencimento) + '</span>';
        return '<li><strong>' + FH.esc(i.descricao) + '</strong>' + (i.pessoa ? ' · ' + FH.esc(i.pessoa) : '') + ' — ' + FH.moeda(i.valor) + extra + '</li>';
    }

    function montar() {
        var corpo = dados.tudo_em_dia
            ? '<div class="aviso-ok"><div class="aviso-ok-icone">✓</div><strong>Tudo em dia!</strong><p>Nenhuma pendência de estoque ou de contas no momento.</p></div>'
            : dados.grupos.map(function (g) {
                var max = 6, itens = g.itens.slice(0, max).map(function (i) { return linhaItem(g, i); }).join('');
                if (g.itens.length > max) { itens += '<li class="muted">… e mais ' + (g.itens.length - max) + '</li>'; }
                return '<section class="aviso-grupo n-' + g.nivel + '"><header><span>' + FH.esc(g.titulo) + ' <span class="cnt">' + g.itens.length + '</span></span>' +
                    (g.total ? '<strong>' + FH.moeda(g.total) + '</strong>' : '') + '</header><ul>' + itens + '</ul>' +
                    '<a class="btn-fh-outline btn-sm" href="' + g.link + '">Ver em ' + (g.link === 'estoque.php' ? 'Estoque' : 'Contas') + ' →</a></section>';
            }).join('');

        var ov = document.createElement('div');
        ov.className = 'fh-overlay aberto'; ov.id = 'modalAvisos';
        ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true'); ov.setAttribute('aria-labelledby', 'tituloAvisos');
        ov.innerHTML = '<div class="fh-modal largo avisos-modal">' +
            '<h3 id="tituloAvisos">' + (dados.tudo_em_dia ? '🔔 Avisos do dia' : '🔔 Pendências que precisam da sua atenção') + '</h3>' +
            '<p class="muted" style="margin-top:-.3rem">' + FH.esc(dados.fala) + ' <small>· ' + FH.esc(dados.gerado_em) + '</small></p>' +
            '<p class="perm-aviso" id="avisoDicaVoz" hidden>🔊 Clique em qualquer lugar desta janela para ouvir o aviso.</p>' +
            '<div class="avisos-corpo">' + corpo + '</div>' +
            '<div class="fh-modal-actions">' +
            '<button type="button" class="btn-fh-ghost" id="avisoVoz"></button>' +
            '<button type="button" class="btn-fh-outline" id="avisoOuvir">🔊 Ouvir de novo</button>' +
            '<button type="button" class="btn-fh" id="avisoFechar">Entendi</button></div></div>';
        document.body.appendChild(ov);

        function rotuloVoz() { FH.el('avisoVoz').textContent = vozLigada() ? '🔇 Silenciar voz' : '🔈 Ativar voz'; }
        rotuloVoz();
        FH.el('avisoVoz').addEventListener('click', function () {
            definirVoz(!vozLigada()); rotuloVoz();
            if (!vozLigada() && window.speechSynthesis) { speechSynthesis.cancel(); } else { falar(true); }
        });
        FH.el('avisoOuvir').addEventListener('click', function () { falar(true); });
        function fechar() { if (window.speechSynthesis) { speechSynthesis.cancel(); } ov.remove(); }
        FH.el('avisoFechar').addEventListener('click', fechar);
        ov.addEventListener('click', function (e) {
            if (e.target === ov) { fechar(); return; }
            if (!falou && vozLigada() && !e.target.closest('button')) { falar(); }   // 1º clique destrava a voz
        });
        document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { fechar(); document.removeEventListener('keydown', esc); } });
        if (!('speechSynthesis' in window)) { FH.el('avisoOuvir').hidden = true; FH.el('avisoVoz').hidden = true; }
    }

    async function iniciar() {
        try { dados = await FH.api('api/avisos.php'); } catch (e) { return; }   // avisos nunca atrapalham a entrada
        montar();
        // As vozes do navegador às vezes carregam depois da página
        var tentou = false;
        function automatico() { if (!tentou) { tentou = true; falar(); } }
        if (window.speechSynthesis && !speechSynthesis.getVoices().length) {
            speechSynthesis.onvoiceschanged = function () { speechSynthesis.onvoiceschanged = null; automatico(); };
            setTimeout(automatico, 800);
        } else { automatico(); }
    }
    iniciar();
})();
