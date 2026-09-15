<?php

declare(strict_types=1);

namespace src\disponible\domain\services;

use src\conceptos\domain\services\CatalogoConceptos;

/** Neto ingresos − gastos de las líneas de remesa (tras excluir 7x ya asignadas). */
final class SobranteRemesa
{
    /**
     * @param list<array{codigo_maestro?:string, importe_cents?:int}|object> $lineas
     */
    public static function cents(array $lineas): int
    {
        $nat = [];
        foreach (CatalogoConceptos::todos() as $c) {
            if ($c['cuenta'] === 'P') {
                $nat[$c['codigo']] = $c['naturaleza'];
            }
        }
        $ingresos = 0;
        $gastos = 0;
        foreach ($lineas as $linea) {
            if (is_object($linea)) {
                $codigo = (string) $linea->codigoMaestro;
                $cents = (int) $linea->importeCents;
            } else {
                $codigo = (string) ($linea['codigo_maestro'] ?? '');
                $cents = (int) ($linea['importe_cents'] ?? 0);
            }
            $n = $nat[$codigo] ?? '';
            if ($n === 'ingreso') {
                $ingresos += $cents;
            } elseif ($n === 'gasto') {
                $gastos += $cents;
            }
        }

        return $ingresos - $gastos;
    }
}
