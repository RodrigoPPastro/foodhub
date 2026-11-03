<?php

/**
 * Utilitário monetário. Regra: calcular SEMPRE em centavos (int) e só
 * converter para reais/texto na borda (JSON, PDF).
 */
final class Dinheiro
{
    /** @param string|int|float|null $valor valor em reais (ex.: "12.50") */
    public static function centavos($valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    public static function reais(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    /** Decimal com ponto, para gravar em colunas DECIMAL(10,2). */
    public static function decimal(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    public static function formatar(int $centavos): string
    {
        return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
    }

    public static function percentual(int $parte, int $total): float
    {
        return $total > 0 ? round($parte * 100 / $total, 1) : 0.0;
    }
}
