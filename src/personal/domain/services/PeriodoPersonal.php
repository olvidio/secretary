<?php

declare(strict_types=1);

namespace src\personal\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\domain\entity\Ejercicio;

/** Periodo mensual del libro personal con fecha de cierre configurable. */
final class PeriodoPersonal
{
    public static function validar(int $anio, int $mes): void
    {
        if ($mes < 1 || $mes > 12 || $anio < 1990 || $anio > 2100) {
            throw new InvalidArgumentException('Año o mes no válido');
        }
    }

    public static function primerDia(int $anio, int $mes): DateTimeImmutable
    {
        self::validar($anio, $mes);
        $d = DateTimeImmutable::createFromFormat('!Y-n-j', $anio . '-' . $mes . '-1');
        if ($d === false) {
            throw new InvalidArgumentException('Mes no válido');
        }

        return $d;
    }

    public static function ultimoDia(int $anio, int $mes): DateTimeImmutable
    {
        return self::primerDia($anio, $mes)->modify('last day of this month');
    }

    /**
     * @return array{desde: DateTimeImmutable, hasta: DateTimeImmutable, fecha_cierre: DateTimeImmutable}
     */
    public static function periodo(
        int $anio,
        int $mes,
        ?int $diaCierreDefecto,
        bool $cierreDiaHabil,
        ?DateTimeImmutable $fechaMes,
    ): array {
        self::validar($anio, $mes);
        $desde = self::primerDia($anio, $mes);
        if ($fechaMes !== null) {
            self::assertEnMes($fechaMes, $anio, $mes);

            return ['desde' => $desde, 'hasta' => $fechaMes, 'fecha_cierre' => $fechaMes];
        }
        $ultimo = self::ultimoDia($anio, $mes);
        if ($diaCierreDefecto === null) {
            return ['desde' => $desde, 'hasta' => $ultimo, 'fecha_cierre' => $ultimo];
        }
        $dia = min($diaCierreDefecto, (int) $ultimo->format('j'));
        $cierre = self::primerDia($anio, $mes)->setDate($anio, $mes, $dia);
        if ($cierreDiaHabil) {
            $cierre = self::siguienteDiaHabil($cierre);
        }

        return ['desde' => $desde, 'hasta' => $cierre, 'fecha_cierre' => $cierre];
    }

    /** Fecha de imputación del asiento de remesa dentro del ejercicio. */
    public static function fechaAsiento(DateTimeImmutable $desde, DateTimeImmutable $hasta, Ejercicio $ejercicio): DateTimeImmutable
    {
        $inicio = $ejercicio->fechaInicio;
        $fin = $ejercicio->fechaFin;
        $corteDesde = $desde > $inicio ? $desde : $inicio;
        $corteHasta = $hasta < $fin ? $hasta : $fin;
        if ($corteDesde > $corteHasta) {
            throw new InvalidArgumentException(
                'Ese mes no cae en el ejercicio ' . $ejercicio->etiqueta
            );
        }

        return $corteHasta;
    }

    public static function siguienteDiaHabil(DateTimeImmutable $fecha): DateTimeImmutable
    {
        while ((int) $fecha->format('N') >= 6) {
            $fecha = $fecha->modify('+1 day');
        }

        return $fecha;
    }

    private static function assertEnMes(DateTimeImmutable $fecha, int $anio, int $mes): void
    {
        if ((int) $fecha->format('Y') !== $anio || (int) $fecha->format('n') !== $mes) {
            throw new InvalidArgumentException('La fecha de cierre debe caer en ese mes');
        }
    }
}
