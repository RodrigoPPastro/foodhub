<?php

/**
 * Formas de pagamento aceitas (FORMA_PAGAMENTO.descricao) — RF22.
 */
class FormaPagamento
{
    public const ROTULOS = [
        'dinheiro' => 'Dinheiro',
        'pix'      => 'PIX',
        'credito'  => 'Cartão de crédito',
        'debito'   => 'Cartão de débito',
        'vale'     => 'Vale',
    ];

    /** @return string[] */
    public static function validas(): array
    {
        return array_keys(self::ROTULOS);
    }

    public static function rotulo(string $forma): string
    {
        return self::ROTULOS[$forma] ?? ucfirst($forma);
    }

    public static function ehCartao(string $forma): bool
    {
        return $forma === 'credito' || $forma === 'debito';
    }
}
