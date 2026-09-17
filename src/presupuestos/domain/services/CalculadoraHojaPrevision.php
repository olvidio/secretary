<?php

declare(strict_types=1);

namespace src\presupuestos\domain\services;

use src\shared\domain\value_objects\Dinero;

/**
 * Arma las filas 613 P de una persona: acumulado, proyección y previsto guardado.
 */
final class CalculadoraHojaPrevision
{
    /**
     * @param list<array{codigo:string,etiqueta:string,codigos:list<string>}> $estructura
     * @param array<string, int> $acumuladoPorCodigo concepto => céntimos
     * @param array<string, int> $anteriorTotalPorCodigo
     * @param array<string, int> $anteriorPeriodoPorCodigo
     * @param array<string, int> $guardadoPorCodigo codigo 613 => céntimos
     * @return list<array<string, mixed>>
     */
    public static function lineas(
        array $estructura,
        array $acumuladoPorCodigo,
        array $anteriorTotalPorCodigo,
        array $anteriorPeriodoPorCodigo,
        array $guardadoPorCodigo,
        int $saldoCcCents,
        int $mesesTranscurridos,
        int $mesesTotales,
    ): array {
        $out = [];
        foreach ($estructura as $def) {
            $codigo = $def['codigo'];
            if (ProyectorPrevisionPersonal::esSaldo($codigo)) {
                $acumulado = $saldoCcCents;
                $anteriorTotal = 0;
                $anteriorPeriodo = 0;
            } else {
                $acumulado = self::suma($acumuladoPorCodigo, $def['codigos']);
                $anteriorTotal = self::suma($anteriorTotalPorCodigo, $def['codigos']);
                $anteriorPeriodo = self::suma($anteriorPeriodoPorCodigo, $def['codigos']);
            }
            $calculado = ProyectorPrevisionPersonal::proyectar(
                $codigo,
                $acumulado,
                $anteriorTotal,
                $anteriorPeriodo,
                $mesesTranscurridos,
                $mesesTotales,
            );
            $guardado = $guardadoPorCodigo[$codigo] ?? null;
            $acumDinero = Dinero::fromCents($acumulado);
            $calcDinero = Dinero::fromCents($calculado);
            $prevDinero = $guardado === null ? null : Dinero::fromCents($guardado);
            $out[] = [
                'codigo' => $codigo,
                'etiqueta' => $def['etiqueta'],
                'grupo' => AgrupadorPrevision613P::grupoDe($codigo),
                'modo' => ProyectorPrevisionPersonal::modo($codigo),
                'acumulado' => $acumDinero->toString(),
                'acumulado_es' => $acumDinero->formatEs(),
                'acumulado_cents' => $acumulado,
                'calculado' => $calcDinero->toString(),
                'calculado_es' => $calcDinero->formatEs(),
                'calculado_cents' => $calculado,
                'previsto' => $prevDinero?->toString(),
                'previsto_es' => $prevDinero?->formatEs(),
                'previsto_cents' => $guardado,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, int> $map
     * @param list<string> $codigos
     */
    private static function suma(array $map, array $codigos): int
    {
        $n = 0;
        foreach ($codigos as $cod) {
            $n += $map[$cod] ?? 0;
        }

        return $n;
    }
}
