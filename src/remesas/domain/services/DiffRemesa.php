<?php

declare(strict_types=1);

namespace src\remesas\domain\services;

use src\remesas\domain\entity\RemesaLinea;
use src\shared\domain\value_objects\Dinero;

final class DiffRemesa
{
    /**
     * @param list<RemesaLinea> $actual
     * @param list<RemesaLinea> $anterior
     * @return list<array<string, mixed>>
     */
    public static function entre(array $actual, array $anterior): array
    {
        $mapA = [];
        foreach ($anterior as $linea) {
            $mapA[$linea->codigoMaestro] = $linea->importeCents;
        }
        $mapB = [];
        foreach ($actual as $linea) {
            $mapB[$linea->codigoMaestro] = $linea->importeCents;
        }
        $codigos = array_values(array_unique(array_merge(array_keys($mapA), array_keys($mapB))));
        sort($codigos, SORT_STRING);
        $out = [];
        foreach ($codigos as $codigo) {
            $prev = $mapA[$codigo] ?? 0;
            $now = $mapB[$codigo] ?? 0;
            if ($prev === $now) {
                continue;
            }
            $delta = $now - $prev;
            $out[] = [
                'codigo_maestro' => $codigo,
                'nombre' => AgregadorRemesaPersonal::nombreMaestro($codigo),
                'anterior_cents' => $prev,
                'actual_cents' => $now,
                'delta_cents' => $delta,
                'anterior_es' => Dinero::fromCents($prev)->formatEs(),
                'actual_es' => Dinero::fromCents($now)->formatEs(),
                'delta_es' => Dinero::fromCents($delta)->formatEs(),
            ];
        }

        return $out;
    }
}
