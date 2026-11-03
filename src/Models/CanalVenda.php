<?php

/**
 * Canais de venda (PEDIDO.canal). Novas plataformas (RNF09) aparecem
 * automaticamente nos relatórios; aqui só se define o rótulo amigável.
 */
class CanalVenda
{
    /** Canais presenciais; todo o resto é considerado "delivery". */
    public const SALAO = ['balcao', 'mesa'];

    private const ROTULOS = [
        'balcao'           => 'Balcão',
        'mesa'             => 'Mesa',
        'delivery_proprio' => 'Delivery próprio',
        'ifood'            => 'iFood',
    ];

    public static function rotulo(string $canal): string
    {
        return self::ROTULOS[$canal] ?? ucfirst(str_replace('_', ' ', $canal));
    }

    public static function ehDelivery(string $canal): bool
    {
        return !in_array($canal, self::SALAO, true);
    }
}
