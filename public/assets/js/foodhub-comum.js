/* FOOD HUB — utilitários compartilhados pelas telas do módulo financeiro. */
window.FH = (function () {
    'use strict';

    var formatoMoeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    var formatoNumero = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 });

    /** Escapa texto vindo do banco antes de colocar em innerHTML (anti-XSS). */
    function esc(valor) {
        return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function moeda(v) { return formatoMoeda.format(Number(v) || 0); }
    function numero(v) { return formatoNumero.format(Number(v) || 0); }
    function percentual(v) { return (Number(v) || 0).toFixed(1).replace('.', ',') + '%'; }
    function dataBr(iso) { var p = String(iso).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso; }

    /** Toast do Bootstrap. tipo: 'ok' | 'erro' | 'info'. */
    function toast(mensagem, tipo) {
        tipo = tipo || 'info';
        var area = document.getElementById('areaToasts');
        var el = document.createElement('div');
        el.className = 'toast align-items-center toast-fh-' + tipo;
        el.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');
        el.innerHTML = '<div class="d-flex"><div class="toast-body"></div>' +
            '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button></div>';
        el.querySelector('.toast-body').textContent = mensagem; // textContent: sem HTML injetado
        area.appendChild(el);
        var t = new bootstrap.Toast(el, { delay: tipo === 'erro' ? 8000 : 4500 });
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        t.show();
    }

    /**
     * fetch JSON padronizado. Lança Error com mensagem pronta para toast.
     * POST envia Content-Type JSON + X-Requested-With (exigidos pelo ApiGuard — anti-CSRF).
     */
    async function api(url, opcoes) {
        opcoes = opcoes || {};
        var config = {
            method: opcoes.method || 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'FoodHub' }
        };
        if (opcoes.corpo !== undefined) {
            config.headers['Content-Type'] = 'application/json';
            config.body = JSON.stringify(opcoes.corpo);
        }

        var resposta;
        try {
            resposta = await fetch(url, config);
        } catch (e) {
            throw new Error('Sem conexão com o servidor. Verifique a rede e tente novamente.');
        }

        var json = null;
        try { json = await resposta.json(); } catch (e) { /* corpo não-JSON */ }

        if (resposta.status === 401) {
            window.location.href = 'login.php';
            throw new Error('Sessão expirada. Faça login novamente.');
        }
        if (!resposta.ok || !json || json.sucesso !== true) {
            throw new Error((json && json.mensagem) || 'Erro inesperado (' + resposta.status + ').');
        }
        return json.dados;
    }

    // Alternância de tema (mesma chave do login).
    (function tema() {
        var botao = document.getElementById('alternarTema');
        if (!botao) { return; }
        var html = document.documentElement;
        function rotular() { botao.textContent = html.getAttribute('data-bs-theme') === 'dark' ? 'Modo claro' : 'Modo escuro'; }
        rotular();
        botao.addEventListener('click', function () {
            var novo = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', novo);
            try { localStorage.setItem('foodhub-tema', novo); } catch (e) {}
            rotular();
        });
    })();

    return { esc: esc, moeda: moeda, numero: numero, percentual: percentual, dataBr: dataBr, toast: toast, api: api };
})();
