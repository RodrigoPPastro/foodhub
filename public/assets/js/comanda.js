/* Impressão de comanda em formato de cupom (janela própria + diálogo de impressão).
 *   FH.imprimirComanda({ titulo, linhas:[texto], itens:[{quantidade, nome, preco_unitario}], total, rotuloTotal,
 *                        pagamentos:[{forma, valor}], carimbo, observacao })  */
(function () {
    'use strict';
    var FORMAS = { dinheiro: 'Dinheiro', pix: 'Pix', credito: 'Crédito', debito: 'Débito', vale: 'Vale' };
    FH.imprimirComanda = function (c) {
        var agora = new Date();
        var itens = (c.itens || []).map(function (i) {
            return '<tr><td>' + i.quantidade + 'x</td><td>' + FH.esc(i.nome) + '<br><small>' + FH.moeda(i.preco_unitario) + ' un.</small></td>' +
                '<td class="v">' + FH.moeda(i.quantidade * i.preco_unitario) + '</td></tr>';
        }).join('');
        var pags = (c.pagamentos || []).filter(function (p) { return Number(p.valor) > 0; });
        var html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>' + FH.esc(c.titulo) + '</title><style>' +
            'body{font-family:"Courier New",monospace;width:300px;margin:0 auto;padding:12px;color:#000;font-size:13px}' +
            'h1{font-size:18px;text-align:center;margin:0}p{margin:2px 0;text-align:center}hr{border:0;border-top:1px dashed #000;margin:8px 0}' +
            'table{width:100%;border-collapse:collapse}td{vertical-align:top;padding:2px 0}td.v{text-align:right;white-space:nowrap}small{color:#333}' +
            '.tot td{font-weight:bold;font-size:15px}@media print{button{display:none}}</style></head><body>' +
            '<h1>FOOD HUB</h1><p>' + FH.esc(c.titulo) + '</p>' +
            (c.linhas || []).map(function (l) { return '<p>' + FH.esc(l) + '</p>'; }).join('') +
            '<p><small>Impresso em ' + agora.toLocaleDateString('pt-BR') + ' ' + agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) + '</small></p><hr>' +
            '<table>' + itens + '</table><hr><table><tr class="tot"><td>' + FH.esc(c.rotuloTotal || 'TOTAL') + '</td><td class="v">' + FH.moeda(c.total) + '</td></tr></table>' +
            (pags.length ? '<hr><p style="text-align:left">Pagamento:</p><table>' + pags.map(function (p) {
                return '<tr><td>' + FH.esc(FORMAS[p.forma] || p.forma) + '</td><td class="v">' + FH.moeda(p.valor) + '</td></tr>'; }).join('') + '</table>' : '') +
            (c.observacao ? '<hr><p style="text-align:left">Obs.: ' + FH.esc(c.observacao) + '</p>' : '') +
            (c.carimbo ? '<hr><p><strong>*** ' + FH.esc(c.carimbo) + ' ***</strong></p>' : '') +
            '<hr><p>Obrigado pela preferência!</p><p><small>Documento sem valor fiscal</small></p>' +
            '<p style="margin-top:10px"><button onclick="window.print()">Imprimir</button></p></body></html>';
        var w = window.open('', '_blank', 'width=380,height=640');
        if (!w) { FH.toast('O navegador bloqueou a janela de impressão. Permita pop-ups para este site.', 'erro'); return; }
        w.document.write(html); w.document.close(); w.focus();
        setTimeout(function () { w.print(); }, 250);
    };
})();
