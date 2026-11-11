<?php

/**
 * RNF11 — contrato do envio de mensagens ao cliente. Hoje só existe a versão SIMULADA.
 * Para usar o WhatsApp de verdade, crie uma classe que implemente esta interface e troque em Adapters::whatsapp().
 */
interface WhatsAppAdapter
{
    /** @return string status gravado em notificacao_cliente.status_envio: 'enviado' | 'simulado' | 'falha' */
    public function enviar(string $telefone, string $mensagem): string;
}
