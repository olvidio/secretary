<?php

declare(strict_types=1);

namespace src\presupuestos\domain\services;

use DateTimeImmutable;
use src\ambito\domain\entity\Ejercicio;

/**
 * Período que sigue al ejercicio abierto, con la misma duración en meses
 * (enero–diciembre, septiembre–agosto u otro). La etiqueta avanza la del
 * ejercicio actual («2026» → «2027», «2025-26» → «2026-27»).
 */
final class SiguientePeriodoEjercicio
{
    /**
     * @return array{etiqueta: string, fecha_inicio: DateTimeImmutable, fecha_fin: DateTimeImmutable}
     */
    public static function calcular(Ejercicio $actual): array
    {
        $meses = max(1, $actual->periodo()->mesesTotales());
        $inicio = $actual->fechaFin->modify('+1 day')->setTime(0, 0);
        $fin = $inicio->modify('+' . $meses . ' months')->modify('-1 day')->setTime(0, 0);

        return [
            'etiqueta' => self::etiqueta($actual->etiqueta, $inicio, $fin),
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
        ];
    }

    private static function etiqueta(string $actual, DateTimeImmutable $inicio, DateTimeImmutable $fin): string
    {
        $actual = trim($actual);
        if (ctype_digit($actual)) {
            return (string) ((int) $actual + 1);
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $actual, $m) === 1) {
            $curso = (int) $m[1] + 1;
            $cierre = ((int) $m[2] + 1) % 100;

            return $curso . '-' . str_pad((string) $cierre, 2, '0', STR_PAD_LEFT);
        }
        if ($inicio->format('Y') === $fin->format('Y')) {
            return $inicio->format('Y');
        }

        return $inicio->format('Y') . '-' . $fin->format('y');
    }
}
