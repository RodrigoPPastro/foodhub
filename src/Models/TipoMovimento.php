<?php

/** MOVIMENTO_ESTOQUE.tipo */
class TipoMovimento
{
    public const ENTRADA = 'entrada';
    public const SAIDA   = 'saida';
    public const PERDA   = 'perda';

    public static function validos(): array
    {
        return [self::ENTRADA, self::SAIDA, self::PERDA];
    }

    /** Entrada soma ao estoque; saída e perda subtraem. */
    public static function sinal(string $tipo): int
    {
        return $tipo === self::ENTRADA ? 1 : -1;
    }
}
