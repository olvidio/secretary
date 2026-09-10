<?php

declare(strict_types=1);

namespace src\cierre\domain\services;

use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

final class RepartoCierre
{
    /**
     * Solo quienes aportan vivienda a generales. El intervalo de exención (llegada
     * o salida a mitad de año) deja fuera esos meses; quien no aporta no entra
     * aunque no tenga exención.
     *
     * @param list<Persona> $personas
     * @return list<array{persona:Persona,importe:Dinero}>
     */
    public static function calcular(Dinero $gastosGenerales, array $personas, int $mes): array
    {
        $residentes = [];
        foreach ($personas as $p) {
            if ($p->exentaEnMes($mes)) {
                continue;
            }
            if (!$p->viviendaAportaGenerales) {
                continue;
            }
            $residentes[] = $p;
        }
        if ($residentes === []) {
            return [];
        }
        $cuota = $gastosGenerales->divInt(count($residentes));
        $out = [];
        foreach ($residentes as $p) {
            $importe = $p->importeViviendaFijo ?? $cuota;
            $out[] = ['persona' => $p, 'importe' => $importe];
        }

        return $out;
    }
}
