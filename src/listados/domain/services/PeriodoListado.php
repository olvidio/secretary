<?php

declare(strict_types=1);

namespace src\listados\domain\services;

use DateTimeImmutable;

/**
 * Periodo que recuerda un listado: el ejercicio actual o el anterior, el mes natural
 * actual o el anterior, u otras fechas fijas. Los relativos se recalculan al abrirlo.
 */
final class PeriodoListado
{
    public const VALIDOS = ['actual', 'anterior', 'mes_actual', 'mes_anterior', 'otro'];

    public static function esMes(string $periodo): bool
    {
        return $periodo === 'mes_actual' || $periodo === 'mes_anterior';
    }

    /**
     * Primer y último día del mes natural que toca según la fecha de hoy.
     *
     * @return array{desde:string,hasta:string} en Y-m-d
     */
    public static function fechasDelMes(string $periodo, DateTimeImmutable $hoy): array
    {
        $primero = $hoy->modify('first day of this month');
        if ($periodo === 'mes_anterior') {
            $primero = $primero->modify('-1 month');
        }

        return [
            'desde' => $primero->format('Y-m-d'),
            'hasta' => $primero->modify('last day of this month')->format('Y-m-d'),
        ];
    }
}
