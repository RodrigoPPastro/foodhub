<?php

require_once __DIR__ . '/WhatsAppAdapter.php';

/** Simulação: não chama nenhuma API; apenas registra no log do servidor. */
class WhatsAppSimuladoAdapter implements WhatsAppAdapter
{
    public function enviar(string $telefone, string $mensagem): string
    {
        error_log(sprintf('[FoodHub][WhatsApp simulado] para %s: %s', $telefone, $mensagem));
        return 'simulado';
    }
}
