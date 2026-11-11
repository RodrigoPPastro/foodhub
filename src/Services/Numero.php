<?php

/**
 * Conversão segura de números digitados no formato brasileiro ("1.234,56",
 * "12,5") ou técnico ("12.5"). Retorna null quando não é um número válido.
 */
final class Numero
{
    /** @param mixed $valor */
    public static function paraFloat($valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return is_finite((float) $valor) ? (float) $valor : null;
        }
        if (!is_string($valor)) {
            return null;
        }
        $v = trim($valor);
        if ($v === '' || !preg_match('/^-?[0-9.,]+$/', $v)) {
            return null;
        }
        if (str_contains($v, ',')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }
        return is_numeric($v) ? (float) $v : null;
    }
}
