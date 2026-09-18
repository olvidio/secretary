<?php

declare(strict_types=1);

namespace src\conceptos\domain\services;

/** Cap. VII del 613 P: códigos 7x editables por centro. */
final class ReglasConceptoPlan
{
    public static function esCapituloVII(string $codigo, string $cuenta): bool
    {
        return $cuenta === 'P' && preg_match('/^7\d{1,2}$/', $codigo) === 1;
    }

    /** @return list<string> */
    public static function naturalezasValidas(): array
    {
        return ['ingreso', 'gasto', 'saldo', 'disponible', 'transferencia'];
    }
}
