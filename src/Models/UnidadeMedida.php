<?php

/** Unidades de medida aceitas em INSUMO.unidade_medida. */
class UnidadeMedida
{
    public const ROTULOS = [
        'kg' => 'Quilograma (kg)', 'g' => 'Grama (g)', 'l' => 'Litro (l)',
        'ml' => 'Mililitro (ml)', 'un' => 'Unidade (un)',
    ];

    public static function validas(): array
    {
        return array_keys(self::ROTULOS);
    }
}
