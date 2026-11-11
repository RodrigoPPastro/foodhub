/* Componente de pagamento dividido (RF22), reutilizado por Pedidos e Mesas.
 * FH.pagamentoUI({ lista: elemento, total: () => número, aoMudar: fn }) devolve { reiniciar, adicionar, pagamentos, soma }.
 * Cada linha = forma + valor; a soma tem de bater com o total (o servidor valida de novo). */
FH.FORMAS = [['dinheiro', 'Dinheiro'], ['pix', 'Pix'], ['credito', 'Cartão de crédito'], ['debito', 'Cartão de débito'], ['vale', 'Vale-refeição']];

FH.pagamentoUI = function (cfg) {
    'use strict';
    var raiz = cfg.lista;

    function opcoes(sel) {
        return FH.FORMAS.map(function (f) { return '<option value="' + f[0] + '"' + (f[0] === sel ? ' selected' : '') + '>' + f[1] + '</option>'; }).join('');
    }
    function paraNumero(t) { var n = parseFloat(String(t).replace(/\./g, '').replace(',', '.')); return isNaN(n) ? 0 : n; }

    function adicionar(forma, valor) {
        var d = document.createElement('div');
        d.className = 'pagto-linha';
        d.innerHTML = '<div class="fh-form-group" style="margin:0"><label>Forma</label><select class="fh-select pg-forma">' + opcoes(forma || 'dinheiro') + '</select></div>' +
            '<div class="fh-form-group" style="margin:0"><label>Valor (R$)</label><input class="fh-input pg-valor" inputmode="decimal" value="' +
            (valor != null ? Number(valor).toFixed(2).replace('.', ',') : '') + '"></div>' +
            '<button type="button" class="btn-fh-ghost btn-sm pg-rem" aria-label="Remover forma de pagamento">×</button>';
        d.querySelector('.pg-rem').addEventListener('click', function () { if (raiz.children.length > 1) { d.remove(); cfg.aoMudar && cfg.aoMudar(); } });
        d.querySelector('.pg-valor').addEventListener('input', function () { cfg.aoMudar && cfg.aoMudar(); });
        raiz.appendChild(d);
        cfg.aoMudar && cfg.aoMudar();
    }
    function pagamentos() {
        return Array.prototype.map.call(raiz.querySelectorAll('.pagto-linha'), function (l) {
            return { forma: l.querySelector('.pg-forma').value, valor: paraNumero(l.querySelector('.pg-valor').value) };
        });
    }
    function soma() { return Math.round(pagamentos().reduce(function (s, p) { return s + p.valor; }, 0) * 100) / 100; }
    function reiniciar(total) { raiz.innerHTML = ''; adicionar('dinheiro', total); }

    return { adicionar: adicionar, pagamentos: pagamentos, soma: soma, reiniciar: reiniciar };
};
