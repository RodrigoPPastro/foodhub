<?php
/** RF20 — documentação e simulador do delivery integrado (iFood/Rappi) + histórico de notificações (RF23). */
require_once __DIR__ . '/../src/Services/AuthGuard.php';
AuthGuard::requireMaster();
$cfg = require __DIR__ . '/../config/integracoes.php';
$layoutTitulo = 'Integrações';
$layoutAtivo  = 'integracoes';
// O simulador precisa do token para chamar o webhook como a plataforma faria (página só do Master).
$layoutHead   = '<script>window.FH_WEBHOOK_TOKEN = ' . json_encode($cfg['webhook_token'], JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
require __DIR__ . '/_layout.php';
$url = (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/api/pedidos_webhook.php';
?>
<div class="page-heading"><div><h1>Integrações</h1><p>Delivery integrado (simulado) e mensagens enviadas aos clientes.</p></div></div>

<div class="fh-tabs" id="abas" role="tablist">
    <button class="fh-tab ativa" data-aba="webhook" role="tab" aria-selected="true">Delivery integrado</button>
    <button class="fh-tab" data-aba="msgs" role="tab" aria-selected="false">Notificações (WhatsApp)</button>
</div>

<div data-painel="webhook" class="fh-tab-painel">
    <div class="fh-grid c2">
        <div class="fh-card-inner">
            <h2>Como a plataforma envia um pedido</h2>
            <p>As plataformas (iFood, Rappi…) chamam este endereço quando entra um pedido. O sistema cria o pedido, cobra os preços do <strong>nosso</strong> cardápio e notifica o cliente. Reenviar o mesmo <code>id_pedido_externo</code> não duplica.</p>
            <p class="fh-label">URL</p><pre style="white-space:pre-wrap;word-break:break-all"><code><?= h($url) ?></code></pre>
            <p class="fh-label">Cabeçalho de segurança</p><pre><code>X-Webhook-Token: <?= h($cfg['webhook_token']) ?></code></pre>
            <p class="fh-hint">O token fica em <code>config/integracoes.php</code> (ou variável FOODHUB_WEBHOOK_TOKEN). Troque antes de publicar.</p>
        </div>
        <div class="fh-card-inner">
            <h2>Simular chegada de pedido</h2>
            <form id="formSim" novalidate>
                <div class="fh-form-row">
                    <div class="fh-form-group"><label for="simPlat">Plataforma</label><select class="fh-select" id="simPlat"></select></div>
                    <div class="fh-form-group"><label for="simPagto">Pagamento (na plataforma)</label>
                        <select class="fh-select" id="simPagto"><option value="pix">Pix</option><option value="credito">Crédito</option><option value="debito">Débito</option><option value="vale">Vale-refeição</option></select></div>
                </div>
                <p class="fh-label" style="margin-bottom:.3rem">Itens do pedido</p>
                <div class="fh-toolbar" style="margin-bottom:.5rem">
                    <div class="fh-form-group" style="flex:1"><label for="simProd">Produto</label><select class="fh-select" id="simProd"></select></div>
                    <div class="fh-form-group" style="width:90px"><label for="simQtd">Qtd</label><input class="fh-input" id="simQtd" type="number" min="1" max="99" value="1"></div>
                    <button class="btn-fh-outline btn-sm" type="button" id="simAdd">+ Adicionar</button>
                </div>
                <div class="table-wrap"><table class="fh-table">
                    <thead><tr><th class="num">Qtd</th><th>Produto</th><th>Setor</th><th class="num">Subtotal</th><th></th></tr></thead>
                    <tbody id="simItens"></tbody>
                </table></div>
                <div class="resumo-valores"><div class="forte"><span>Total do pedido</span><strong id="simTotal">R$ 0,00</strong></div></div>
                <div class="fh-form-row">
                    <div class="fh-form-group"><label for="simNome">Cliente</label><input class="fh-input" id="simNome" value="Cliente Teste"></div>
                    <div class="fh-form-group"><label for="simTel">Telefone (WhatsApp)</label><input class="fh-input" id="simTel" value="(+55)21-988887777"></div>
                </div>
                <div class="fh-form-group"><label for="simEnd">Endereço</label><input class="fh-input" id="simEnd" value="Rua das Flores, 100, Centro"></div>
                <div class="fh-form-group"><label for="simObs">Observação do cliente</label><input class="fh-input" id="simObs" maxlength="200" placeholder="Ex.: tocar o interfone"></div>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <button class="btn-fh" type="submit">Enviar pedido simulado</button>
                    <button class="btn-fh-gold" type="button" id="btnAleatorio">🎲 Pedido aleatório</button>
                    <button class="btn-fh-outline" type="button" id="btnRepetir">Reenviar o último (teste de duplicidade)</button>
                </div>
            </form>
            <pre id="simResp" class="fh-hint" style="white-space:pre-wrap;margin-top:1rem"></pre>
        </div>
    </div>
</div>

<div data-painel="msgs" class="fh-tab-painel" hidden>
    <div class="fh-card-inner">
        <h2>Últimas mensagens <small>simulação do envio por WhatsApp</small></h2>
        <div class="table-wrap"><table class="fh-table">
            <thead><tr><th>Quando</th><th>Pedido</th><th>Tipo</th><th>Mensagem</th><th>Envio</th></tr></thead>
            <tbody id="tbMsgs"><tr><td colspan="5" class="vazio-msg">Carregando…</td></tr></tbody>
        </table></div>
    </div>
</div>
<?php
$layoutScripts = ['assets/js/integracoes.js'];
require __DIR__ . '/_layout_fim.php';
