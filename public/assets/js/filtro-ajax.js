/* RNF08 — telas de consulta filtram via AJAX, sem recarregar a página.
 * Um <form data-ajax-filtro="idDoBloco"> (ou link com o mesmo atributo) busca a mesma página no servidor
 * e substitui só o bloco indicado. Sem JavaScript, o formulário continua funcionando normalmente. */
(function () {
    'use strict';
    var carregando = false;

    async function atualizar(url, idBloco, focoId) {
        if (carregando) { return; }
        var bloco = document.getElementById(idBloco);
        if (!bloco) { location.href = url; return; }
        carregando = true;
        bloco.style.opacity = '.55';
        try {
            var r = await fetch(url, { headers: { 'X-Requested-With': 'FoodHub' }, credentials: 'same-origin' });
            if (!r.ok || r.redirected) { location.href = url; return; }   // sessão expirada etc.: segue o fluxo normal
            var doc = new DOMParser().parseFromString(await r.text(), 'text/html');
            var novo = doc.getElementById(idBloco);
            if (!novo) { location.href = url; return; }
            bloco.replaceWith(novo);
            history.replaceState(null, '', url);
            if (focoId && document.getElementById(focoId)) {
                var campo = document.getElementById(focoId);
                campo.focus();
                if (campo.setSelectionRange && typeof campo.value === 'string') { campo.setSelectionRange(campo.value.length, campo.value.length); }
            }
        } catch (e) {
            FH.toast('Não foi possível filtrar agora. Tente novamente.', 'erro');
            bloco.style.opacity = '';
        } finally { carregando = false; }
    }

    document.addEventListener('submit', function (ev) {
        var f = ev.target.closest('form[data-ajax-filtro]'); if (!f) { return; }
        ev.preventDefault();
        var params = new URLSearchParams(new FormData(f));
        var url = (f.getAttribute('action') || location.pathname) + (params.toString() ? '?' + params.toString() : '');
        var ativo = document.activeElement && document.activeElement.id && f.contains(document.activeElement) ? document.activeElement.id : null;
        atualizar(url, f.dataset.ajaxFiltro, ativo || (f.querySelector('input[type=text],input:not([type])') || {}).id);
    });
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest('a[data-ajax-filtro]'); if (!a) { return; }
        ev.preventDefault();
        atualizar(a.getAttribute('href'), a.dataset.ajaxFiltro, null);
    });
})();
