<?php

declare(strict_types=1);

namespace src\presupuestos\domain\services;

use src\shared\domain\value_objects\Dinero;

/**
 * Proyección a cierre de ejercicio para la hoja de previsión personal.
 *
 * - Recurrente: acumulado × meses totales / meses transcurridos.
 * - Puntual (p. ej. 24 ca/crt/cv): acumulado más lo que en el ejercicio
 *   anterior cayó después del mismo tramo de meses; si este año aún no
 *   hay nada y el año pasado ya había pasado el evento, se toma el total
 *   anterior.
 * - Saldo (9): el saldo actual de la c/c, sin extrapolar.
 */
final class ProyectorPrevisionPersonal
{
    /** @var list<string> */
    public const PUNTUALES = ['12', '24', '4', '51', '52'];

    public const SALDO = '9';

    public static function esPuntual(string $codigo): bool
    {
        return in_array($codigo, self::PUNTUALES, true);
    }

    public static function esSaldo(string $codigo): bool
    {
        return $codigo === self::SALDO;
    }

    public static function modo(string $codigo): string
    {
        if (self::esSaldo($codigo)) {
            return 'saldo';
        }
        if (self::esPuntual($codigo)) {
            return 'puntual';
        }

        return 'lineal';
    }

    public static function proyectar(
        string $codigo,
        int $acumuladoCents,
        int $anteriorTotalCents,
        int $anteriorMismoPeriodoCents,
        int $mesesTranscurridos,
        int $mesesTotales,
    ): int {
        if (self::esSaldo($codigo)) {
            return $acumuladoCents;
        }
        if (self::esPuntual($codigo)) {
            return self::puntual($acumuladoCents, $anteriorTotalCents, $anteriorMismoPeriodoCents);
        }

        return self::lineal($acumuladoCents, $anteriorTotalCents, $mesesTranscurridos, $mesesTotales);
    }

    public static function lineal(
        int $acumuladoCents,
        int $anteriorTotalCents,
        int $mesesTranscurridos,
        int $mesesTotales,
    ): int {
        if ($mesesTranscurridos <= 0) {
            return $anteriorTotalCents;
        }
        $totales = max(1, $mesesTotales);

        return Dinero::fromCents($acumuladoCents)
            ->mulRatio((string) $totales, (string) $mesesTranscurridos)
            ->toCents();
    }

    public static function puntual(
        int $acumuladoCents,
        int $anteriorTotalCents,
        int $anteriorMismoPeriodoCents,
    ): int {
        $restoAnterior = $anteriorTotalCents - $anteriorMismoPeriodoCents;
        if ($restoAnterior < 0) {
            $restoAnterior = 0;
        }
        if ($acumuladoCents === 0 && $restoAnterior === 0) {
            return $anteriorTotalCents;
        }

        return $acumuladoCents + $restoAnterior;
    }
}
