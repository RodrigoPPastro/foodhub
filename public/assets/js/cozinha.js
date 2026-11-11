/* Painel de preparo de um setor (Cozinha ou Bar — atributo data-setor do grid).
 * Cada setor inicia e conclui só os próprios itens; o servidor decide quando o pedido inteiro fica pronto. */
(function () {
    'use strict';
    var grid = FH.el('gridCozinha'), setor = grid.dataset.setor || 'cozinha', ocupado = false;

    function ticket(p) {
        var aguardando = p.estado_setor === 'aguardando';
        var itens = p.itens.map(function (i) {
            return '<li><strong>' + i.quantidade + '×</strong> ' + FH.esc(i.nome) + (i.observacao ? '<br><small>↳ ' + FH.esc(i.observacao) + '</small>' : '') + '</li>';
        }).join('');
        var onde = p.mesa_numero ? 'Mesa ' + p.mesa_numero + (p.cliente ? ' · ' + FH.esc(p.cliente) : '') : FH.esc(p.canal_rotulo);
        var outros = p.outros_setores.map(function (o) {
            return '<span class="setor-tag s-' + o.setor + '">' + FH.esc(o.setor_rotulo) + ': ' + FH.esc(o.estado_rotulo) + '</span>';
        }).join(' ');
        if (p.sem_preparo.length && !p.presencial) {
            outros += ' <span class="setor-tag s-nenhum" title="Itens sem preparo que vão junto na entrega">+ ' +
                p.sem_preparo.map(function (i) { return i.quantidade + '× ' + FH.esc(i.nome); }).join(', ') + '</span>';
        }
        return '<article class="ticket ' + (p.atrasado ? 'atrasado' : '') + (aguardando ? ' aguardando' : '') + '">' +
            '<header><strong>#' + p.id_pedido + '</strong><span class="cronometro">' + (aguardando ? 'Aguardando' : p.minutos_em_preparo + ' min') + '</span></header>' +
            '<div class="meta"><span class="canal-tag">' + onde + '</span> ' + (p.atrasado ? '<span class="tag-atraso">ATRASADO</span>' : '') +
            ' <span class="muted">' + FH.horaBr(p.data_hora) + '</span></div>' +
            '<ul>' + itens + '</ul>' + (p.observacao ? '<p class="fh-hint">Obs.: ' + FH.esc(p.observacao) + '</p>' : '') +
            (outros ? '<p class="ticket-outros">' + outros + '</p>' : '') +
            (aguardando ? '<button class="btn-fh btn-sm" data-id="' + p.id_pedido + '" data-etapa="iniciar">Iniciar preparo</button>'
                        : '<button class="btn-fh-gold btn-sm" data-id="' + p.id_pedido + '" data-etapa="pronto">Marcar pronto</button>') +
            '</article>';
    }

    async function carregar() {
        if (ocupado) { return; }
        try {
            var d = await FH.api('api/pedidos.php?visao=cozinha&setor=' + encodeURIComponent(setor));
            grid.innerHTML = d.pedidos.length ? d.pedidos.map(ticket).join('') : '<p class="vazio-msg">Nenhum item na fila. 🎉</p>';
            FH.el('cozAtualizado').textContent = 'Atualizado às ' + d.gerado_em;
        } catch (e) { grid.innerHTML = '<p class="vazio-msg">' + FH.esc(e.message) + '</p>'; }
    }
    grid.addEventListener('click', async function (ev) {
        var b = ev.target.closest('button[data-id]'); if (!b) { return; }
        b.disabled = true; ocupado = true;
        try {
            var p = await FH.api('api/pedidos.php', { method: 'POST', corpo: { acao: 'preparo', id_pedido: Number(b.dataset.id), setor: setor, etapa: b.dataset.etapa } });
            FH.toast(b.dataset.etapa === 'iniciar' ? 'Pedido #' + b.dataset.id + ': preparo iniciado.'
                : (p && p.status === 'pronto' ? 'Pedido #' + b.dataset.id + ' completo e pronto!' : 'Pedido #' + b.dataset.id + ': sua parte está pronta.'), 'ok');
        } catch (e) { FH.toast(e.message, 'erro'); }
        ocupado = false; carregar();
    });
    carregar(); setInterval(carregar, 10000);
})();
