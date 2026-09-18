<?php

declare(strict_types=1);

namespace src\legal\application;

final class LecturaAceptacion
{
    public static function marcada(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_int($valor) || is_float($valor)) {
            return (int) $valor === 1;
        }
        $s = strtolower(trim((string) $valor));

        return in_array($s, ['1', 'true', 'on', 'si', 'sí', 'yes'], true);
    }
}
