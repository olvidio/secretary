<?php

declare(strict_types=1);

namespace src\personal\domain\services;

use InvalidArgumentException;
use src\personal\domain\contracts\LectorCsvBanco;

/** Bancos con lector de extracto CSV. El desplegable sale de aquí; la detección automática vendrá después. */
final class CatalogoBancosCsv
{
    /**
     * @return list<array{id:string, nombre:string}>
     */
    public static function todos(): array
    {
        return [
            ['id' => 'n26', 'nombre' => 'N26'],
            ['id' => 'caixabank', 'nombre' => 'CaixaBank'],
            ['id' => 'bbva', 'nombre' => 'BBVA'],
            ['id' => 'sabadell', 'nombre' => 'Banco Sabadell'],
        ];
    }

    public static function lector(string $banco): LectorCsvBanco
    {
        $id = strtolower(trim($banco));
        return match ($id) {
            'n26' => new LectorCsvN26(),
            'caixabank' => new LectorCsvCaixaBank(),
            'bbva' => new LectorCsvBbva(),
            'sabadell' => new LectorCsvSabadell(),
            default => throw new InvalidArgumentException('Banco no soportado: elija uno de la lista'),
        };
    }

    public static function existe(string $banco): bool
    {
        $id = strtolower(trim($banco));
        foreach (self::todos() as $fila) {
            if ($fila['id'] === $id) {
                return true;
            }
        }

        return false;
    }
}
