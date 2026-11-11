<?php

require_once __DIR__ . '/WhatsAppSimuladoAdapter.php';
require_once __DIR__ . '/PlataformaExternaSimuladaAdapter.php';

/** Ponto ÚNICO onde as integrações são escolhidas (RNF11). Para ligar uma real, retorne-a aqui. */
final class Adapters
{
    public static function whatsapp(): WhatsAppAdapter { return new WhatsAppSimuladoAdapter(); }
    public static function plataformaExterna(): PlataformaExternaAdapter { return new PlataformaExternaSimuladaAdapter(); }
}
