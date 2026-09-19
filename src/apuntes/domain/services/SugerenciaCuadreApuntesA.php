<?php

declare(strict_types=1);

namespace src\apuntes\domain\services;

use src\shared\domain\value_objects\Dinero;

/**
 * Propone el apunte P/A que falta para cuadrar los apuntes A de una persona.
 * Ingreso 111 si sobran gastos; vivienda 211 o 212 si sobran ingresos (p. ej. falta el gasto
 * tras un G/11 o un cierre automático).
 */
final class SugerenciaCuadreApuntesA
{
    /**
     * @param array<string, string> $naturalezasG codigo => naturaleza (libro G)
     * @param list<array<string, mixed>> $apuntesG apuntes G de la persona (origen A)
     * @return array<string, string>|null
     */
    public static function sugerir(
        bool $cuadrado,
        bool $cuadradoAntes,
        bool $hayApuntesFecha,
        bool $soloGastosFecha,
        int $saldoFecha,
        int $saldoTotal,
        string $fecha,
        array $apuntesG,
        array $naturalezasG,
        bool $viviendaAportaGenerales,
        string $tipoCierre = 'vivienda',
    ): ?array {
        if ($cuadrado) {
            return null;
        }

        if ($cuadradoAntes && $hayApuntesFecha && $soloGastosFecha && $saldoFecha > 0) {
            return self::apunte('111', $saldoFecha, 'solo_gastos_fecha');
        }

        if ($saldoTotal > 0) {
            return self::apunte('111', $saldoTotal, 'saldo_positivo');
        }

        if ($saldoTotal < 0) {
            $concepto = self::conceptoViviendaFaltante(
                abs($saldoTotal),
                $fecha,
                $apuntesG,
                $naturalezasG,
                $viviendaAportaGenerales,
                $tipoCierre,
            );
            if ($concepto !== null) {
                return self::apunte($concepto, abs($saldoTotal), 'saldo_negativo_vivienda');
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $naturalezasG
     * @param list<array<string, mixed>> $apuntesG
     */
    public static function conceptoViviendaFaltante(
        int $objetivoCents,
        string $fecha,
        array $apuntesG,
        array $naturalezasG,
        bool $viviendaAportaGenerales,
        string $tipoCierre,
    ): ?string {
        if ($objetivoCents <= 0) {
            return null;
        }

        if ($tipoCierre === 'necesidades') {
            return '6';
        }

        $candidato = self::candidatoG($objetivoCents, $fecha, $apuntesG);
        if ($candidato !== null) {
            $nat = $naturalezasG[(string) ($candidato['concepto_codigo'] ?? '')] ?? '';
            $codigo = (string) ($candidato['concepto_codigo'] ?? '');
            if ($nat === 'ingreso' || $codigo === '11') {
                return ClasificarConceptoViviendaP::GENERAL;
            }
            if ($nat === 'gasto') {
                return ClasificarConceptoViviendaP::GENERAL;
            }
        }

        return $viviendaAportaGenerales
            ? ClasificarConceptoViviendaP::GENERAL
            : ClasificarConceptoViviendaP::PERSONAL;
    }

    /**
     * @param list<array<string, mixed>> $apuntesG
     * @return array<string, mixed>|null
     */
    private static function candidatoG(int $objetivoCents, string $fecha, array $apuntesG): ?array
    {
        $exactos = [];
        foreach ($apuntesG as $a) {
            if (strtoupper((string) ($a['origen'] ?? '')) !== 'A') {
                continue;
            }
            if (self::mismaCantidad($a, $objetivoCents)) {
                $exactos[] = $a;
            }
        }
        if ($exactos === []) {
            return null;
        }

        usort($exactos, static function (array $x, array $y) use ($fecha): int {
            $fx = (string) ($x['fecha'] ?? '');
            $fy = (string) ($y['fecha'] ?? '');
            $px = $fx === $fecha ? 0 : 1;
            $py = $fy === $fecha ? 0 : 1;
            if ($px !== $py) {
                return $px <=> $py;
            }
            $ox = $x['origen'] === 'A' ? 0 : 1;
            $oy = $y['origen'] === 'A' ? 0 : 1;

            return $ox <=> $oy;
        });

        $gastos = array_values(array_filter(
            $exactos,
            static fn (array $a): bool => ((string) ($a['concepto_codigo'] ?? '')) !== '11'
                && self::importePositivo($a),
        ));
        if ($gastos !== []) {
            return $gastos[0];
        }

        return $exactos[0];
    }

    /** @param array<string, mixed> $a */
    private static function mismaCantidad(array $a, int $objetivoCents): bool
    {
        return Dinero::fromInput((string) ($a['cantidad'] ?? '0'))->toCents() === $objetivoCents;
    }

    /** @param array<string, mixed> $a */
    private static function importePositivo(array $a): bool
    {
        return Dinero::fromInput((string) ($a['cantidad'] ?? '0'))->toCents() > 0;
    }

    /** @return array<string, string> */
    private static function apunte(string $concepto, int $cents, string $motivo): array
    {
        $importe = Dinero::fromCents($cents);

        return [
            'concepto_codigo' => $concepto,
            'cantidad' => $importe->toString(),
            'cantidad_es' => $importe->formatEs(),
            'motivo' => $motivo,
        ];
    }
}
