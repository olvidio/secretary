<?php

declare(strict_types=1);

namespace src\remesas\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\domain\entity\Ejercicio;

/** Primer y último día del mes, y fecha de imputación del asiento de remesa. */
final class PeriodoMesRemesa
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

    /** Último día del mes que cae dentro del ejercicio (D11). */
    public static function fechaAsiento(int $anio, int $mes, Ejercicio $ejercicio): DateTimeImmutable
    {
        $desde = self::primerDia($anio, $mes);
        $hasta = self::ultimoDia($anio, $mes);
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
}
