<?php
/** Parâmetros do módulo de Pedidos/Integrações (RF20, RNF11). Podem vir de variáveis de ambiente. */
return [
    // Token que a plataforma externa (simulada) envia no cabeçalho X-Webhook-Token. TROQUE antes de publicar.
    'webhook_token' => getenv('FOODHUB_WEBHOOK_TOKEN') ?: 'foodhub-demo-token',
    // Pedido "em preparo" há mais que isto (minutos) é destacado como atrasado.
    'atraso_minutos' => (int) (getenv('FOODHUB_ATRASO_MIN') ?: 20),
];
