<?php

/**
 * Setor de produção de cada produto (PRODUTO.setor_preparo / ITEM_PEDIDO.setor_preparo).
 *   cozinha → aparece só na tela da Cozinha
 *   bar     → aparece só na tela do Bar
 *   nenhum  → pronta entrega (água, lata): não passa por nenhuma tela de preparo
 *
 * Andamento de cada item (ITEM_PEDIDO.status_item):
 *   aguardando → em preparo → pronto → entregue
 *   Item sem preparo nasce "entregue" no salão (mesa/balcão) e "pronto" no delivery (vai na sacola).
 */
class SetorPreparo
{
    public const COZINHA = 'cozinha';
    public const BAR     = 'bar';
    public const NENHUM  = 'nenhum';

    public const TODOS       = [self::COZINHA, self::BAR, self::NENHUM];
    public const COM_PREPARO = [self::COZINHA, self::BAR];

    public const ROTULOS = [self::COZINHA => 'Cozinha', self::BAR => 'Bar', self::NENHUM => 'Sem preparo'];

    /* status_item */
    public const ITEM_AGUARDANDO = 'aguardando';
    public const ITEM_EM_PREPARO = 'em preparo';
    public const ITEM_PRONTO     = 'pronto';
    public const ITEM_ENTREGUE   = 'entregue';

    public const ROTULOS_ITEM = [
        self::ITEM_AGUARDANDO => 'Aguardando', self::ITEM_EM_PREPARO => 'Em preparo',
        self::ITEM_PRONTO => 'Pronto', self::ITEM_ENTREGUE => 'Entregue',
    ];

    public static function valido(string $setor): bool
    {
        return in_array($setor, self::TODOS, true);
    }

    public static function temPreparo(string $setor): bool
    {
        return in_array($setor, self::COM_PREPARO, true);
    }

    public static function rotulo(string $setor): string
    {
        return self::ROTULOS[$setor] ?? ucfirst($setor);
    }

    /** Status inicial do item ao entrar no pedido. */
    public static function statusInicial(string $setor, bool $presencial): string
    {
        if (self::temPreparo($setor)) {
            return self::ITEM_AGUARDANDO;
        }
        return $presencial ? self::ITEM_ENTREGUE : self::ITEM_PRONTO;
    }
}
