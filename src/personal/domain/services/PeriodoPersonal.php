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
     * @return array{0: int, 1: int}
     */
    public static function mesAnterior(int $anio, int $mes): array
    {
        if ($mes === 1) {
            return [$anio - 1, 12];
        }

        return [$anio, $mes - 1];
    }

    /**
     * El periodo empieza el día siguiente al cierre del mes anterior.
     * Si ese cierre es el último día, el inicio coincide con el día 1.
     *
     * @return array{desde: DateTimeImmutable, hasta: DateTimeImmutable, fecha_cierre: DateTimeImmutable}
     */
    public static function periodo(
        int $anio,
        int $mes,
        ?int $diaCierreDefecto,
        bool $cierreDiaHabil,
        ?DateTimeImmutable $fechaMes,
        ?DateTimeImmutable $fechaMesAnterior = null,
    ): array {
        self::validar($anio, $mes);
        $hasta = self::fechaCierre($anio, $mes, $diaCierreDefecto, $cierreDiaHabil, $fechaMes);
        [$anioAnt, $mesAnt] = self::mesAnterior($anio, $mes);
        $cierreAnt = self::fechaCierre($anioAnt, $mesAnt, $diaCierreDefecto, $cierreDiaHabil, $fechaMesAnterior);
        $desde = $cierreAnt->modify('+1 day');
        if ($desde > $hasta) {
            throw new InvalidArgumentException('El inicio del periodo queda después del cierre');
        }

        return ['desde' => $desde, 'hasta' => $hasta, 'fecha_cierre' => $hasta];
    }

    public static function fechaCierre(
        int $anio,
        int $mes,
        ?int $diaCierreDefecto,
        bool $cierreDiaHabil,
        ?DateTimeImmutable $fechaMes,
    ): DateTimeImmutable {
        if ($fechaMes !== null) {
            self::assertEnMes($fechaMes, $anio, $mes);

            return $fechaMes;
        }
        $ultimo = self::diaUno($anio, $mes)->modify('last day of this month');
        if ($diaCierreDefecto === null) {
            return $ultimo;
        }
        $dia = min($diaCierreDefecto, (int) $ultimo->format('j'));
        $cierre = self::diaUno($anio, $mes)->setDate($anio, $mes, $dia);
        if ($cierreDiaHabil) {
            $cierre = self::siguienteDiaHabil($cierre);
        }

        return $cierre;
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

    private static function diaUno(int $anio, int $mes): DateTimeImmutable
    {
        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException('Mes no válido');
        }
        $d = DateTimeImmutable::createFromFormat('!Y-n-j', $anio . '-' . $mes . '-1');
        if ($d === false) {
            throw new InvalidArgumentException('Mes no válido');
        }

        return $d;
    }

    private static function assertEnMes(DateTimeImmutable $fecha, int $anio, int $mes): void
    {
        if ((int) $fecha->format('Y') !== $anio || (int) $fecha->format('n') !== $mes) {
            throw new InvalidArgumentException('La fecha de cierre debe caer en ese mes');
        }
    }
}
