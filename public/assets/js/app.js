/* FOOD HUB — utilitários compartilhados por todas as telas autenticadas.
 * Sem dependências. Expõe window.FH (e o alias window.fhToast usado por páginas antigas). */
window.FH = (function () {
    'use strict';

    var fmtMoeda  = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    var fmtNumero = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 });

    /** Escapa texto antes de colocar em innerHTML (anti-XSS). */
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function moeda(v) { return fmtMoeda.format(Number(v) || 0); }
    function numero(v) { return fmtNumero.format(Number(v) || 0); }
    function percentual(v) { return (Number(v) || 0).toFixed(1).replace('.', ',') + '%'; }
    function dataBr(iso) { var p = String(iso).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso; }
    function horaBr(dt) { var m = String(dt || '').match(/(\d{2}):(\d{2})/); return m ? m[0] : ''; }
    function el(id) { return document.getElementById(id); }
    function debounce(fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; }

    /** Toast (RF12/RNF07). tipo: 'ok' | 'erro' | 'info'. */
    function toast(msg, tipo) {
        var area = el('fhToastArea');
        if (!area) { return; }
        var t = document.createElement('div');
        t.className = 'fh-toast fh-toast--' + (tipo || 'info');
        t.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');
        var span = document.createElement('span');
        span.textContent = msg;                   // textContent: nunca injeta HTML
        var fechar = document.createElement('button');
        fechar.type = 'button'; fechar.setAttribute('aria-label', 'Fechar aviso'); fechar.textContent = '×';
        t.appendChild(span); t.appendChild(fechar); area.appendChild(t);
        function remover() { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 300); }
        fechar.addEventListener('click', remover);
        requestAnimationFrame(function () { t.classList.add('show'); });
        setTimeout(remover, tipo === 'erro' ? 7000 : 4000);
    }

    /** Modal de confirmação (substitui confirm() nativo). Retorna Promise<boolean>. */
    function confirmar(titulo, texto, rotuloOk) {
        return new Promise(function (resolve) {
            var ov = el('fhConfirmOverlay');
            el('fhConfirmTitulo').textContent = titulo;
            el('fhConfirmTexto').textContent = texto;
            var ok = el('fhConfirmOk'), cancelar = el('fhConfirmCancelar');
            ok.textContent = rotuloOk || 'Confirmar';
            ov.classList.add('aberto'); ok.focus();
            function fim(v) {
                ov.classList.remove('aberto');
                ok.removeEventListener('click', onOk); cancelar.removeEventListener('click', onCancel);
                document.removeEventListener('keydown', onKey); ov.removeEventListener('click', onFora);
                resolve(v);
            }
            function onOk() { fim(true); } function onCancel() { fim(false); }
            function onKey(e) { if (e.key === 'Escape') { fim(false); } }
            function onFora(e) { if (e.target === ov) { fim(false); } }
            ok.addEventListener('click', onOk); cancelar.addEventListener('click', onCancel);
            document.addEventListener('keydown', onKey); ov.addEventListener('click', onFora);
        });
    }

    /** Abre/fecha um modal próprio da página (.fh-overlay). */
    function abrirModal(id) {
        var ov = el(id); ov.classList.add('aberto');
        var primeiro = ov.querySelector('input:not([type=hidden]),select,textarea,button');
        if (primeiro) { primeiro.focus(); }
    }
    function fecharModal(id) { el(id).classList.remove('aberto'); }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { document.querySelectorAll('.fh-overlay.aberto').forEach(function (o) { if (o.id !== 'fhConfirmOverlay') { o.classList.remove('aberto'); } }); }
    });
    document.addEventListener('click', function (e) {
        if (e.target.classList && e.target.classList.contains('fh-overlay') && e.target.id !== 'fhConfirmOverlay') { e.target.classList.remove('aberto'); }
        var alvo = e.target.closest && e.target.closest('[data-fechar-modal]');
        if (alvo) { fecharModal(alvo.getAttribute('data-fechar-modal')); }
    });

    /**
     * fetch JSON padronizado. Lança Error com mensagem pronta para toast.
     * POST envia Content-Type JSON + X-Requested-With (exigidos pelo ApiGuard — anti-CSRF).
     * Contrato de resposta: { sucesso: bool, dados: ..., mensagem: string }.
     */
    async function api(url, opcoes) {
        opcoes = opcoes || {};
        var cfg = {
            method: opcoes.method || 'GET', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'FoodHub' }
        };
        if (opcoes.formData) {
            cfg.method = 'POST'; cfg.body = opcoes.formData;       // multipart: o navegador define o Content-Type
        } else if (opcoes.corpo !== undefined) {
            cfg.headers['Content-Type'] = 'application/json';
            cfg.body = JSON.stringify(opcoes.corpo);
        }
        var resp;
        try { resp = await fetch(url, cfg); }
        catch (e) { throw new Error('Sem conexão com o servidor. Verifique a rede e tente novamente.'); }

        var json = null;
        try { json = await resp.json(); } catch (e) { /* corpo não-JSON */ }

        if (resp.status === 401) {
            window.location.href = 'login.php';
            throw new Error('Sessão expirada. Faça login novamente.');
        }
        if (!resp.ok || !json || json.sucesso !== true) {
            throw new Error((json && json.mensagem) || 'Erro inesperado (' + resp.status + ').');
        }
        return json.dados;
    }

    /** Abas simples: botões [data-aba] ↔ painéis [data-painel]. */
    function abas(raiz) {
        var botoes = raiz.querySelectorAll('[data-aba]');
        botoes.forEach(function (b) {
            b.addEventListener('click', function () {
                botoes.forEach(function (x) { x.classList.toggle('ativa', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
                raiz.querySelectorAll('[data-painel]').forEach(function (p) { p.hidden = p.getAttribute('data-painel') !== b.getAttribute('data-aba'); });
            });
        });
    }

    function classeStatus(s) { return 'st-' + String(s).replace(/\s+/g, '-'); }
    var ROTULO_STATUS = { 'novo': 'Novo', 'em preparo': 'Em preparo', 'pronto': 'Pronto', 'saiu': 'Saiu p/ entrega', 'entregue': 'Entregue', 'cancelado': 'Cancelado' };
    function rotuloStatus(s) { return ROTULO_STATUS[s] || s; }

    return {
        esc: esc, moeda: moeda, numero: numero, percentual: percentual, dataBr: dataBr, horaBr: horaBr,
        el: el, debounce: debounce, toast: toast, confirmar: confirmar, api: api,
        abrirModal: abrirModal, fecharModal: fecharModal, abas: abas,
        classeStatus: classeStatus, rotuloStatus: rotuloStatus
    };
})();

window.fhToast = window.FH.toast; /* compatibilidade com fhToast(msg, tipo) */

/* Acessibilidade (tema, contraste, fonte): ver assets/js/acessibilidade.js, carregado no <head> do layout. */
