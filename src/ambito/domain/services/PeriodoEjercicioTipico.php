<?php

declare(strict_types=1);

namespace src\ambito\domain\services;

use DateTimeImmutable;

/** Fechas habituales al dar de alta un ejercicio (año natural o curso sept–ago). */
final class PeriodoEjercicioTipico
{
    public const MODO_ANO = 'Año';
    public const MODO_CURSO = 'Curso';

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable} [fecha_inicio, fecha_fin]
     */
    public static function fechas(int $anio, string $modo): array
    {
        if ($modo === self::MODO_CURSO) {
            return [
                new DateTimeImmutable(sprintf('%d-09-01', $anio)),
                new DateTimeImmutable(sprintf('%d-08-31', $anio + 1)),
            ];
        }

        return [
            new DateTimeImmutable(sprintf('%d-01-01', $anio)),
            new DateTimeImmutable(sprintf('%d-12-31', $anio)),
        ];
    }

    public static function modoDesdeFechas(DateTimeImmutable $inicio, DateTimeImmutable $fin): string
    {
        $inicioAnio = (int) $inicio->format('Y');
        if ($fin->format('m-d') === '12-31' && (int) $fin->format('Y') === $inicioAnio) {
            return self::MODO_ANO;
        }
        if ($fin->format('m-d') === '08-31' && (int) $fin->format('Y') === $inicioAnio + 1) {
            return self::MODO_CURSO;
        }

        return self::MODO_ANO;
    }
}
