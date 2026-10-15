<?php

declare(strict_types=1);

namespace src\cierre\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

/**
 * Reduce la deuda de vivienda sin un ingreso nuevo.
 * P: debe deuda / haber la contrapartida (superávit de la persona, o saldo contable).
 * G: debe la contrapartida (superávit colectivo, o regularización) / haber deudores.
 */
final class ConstructorAsientosLiquidacionDeuda
{
    /**
     * @return array{0: Asiento, 1: Asiento}
     */
    public static function construir(
        int $ejercicioId,
        int $personaId,
        DateTimeImmutable $fecha,
        int $deudaId,
        int $contraPId,
        int $deudoresId,
        int $contraGId,
        int $cents,
        string $glosa,
        string $tipo = 'liquidacion_deuda',
    ): array {
        if ($cents <= 0) {
            throw new InvalidArgumentException('El importe a saldar debe ser positivo');
        }
        if (!in_array($tipo, ['liquidacion_deuda', CuentasDeudaVivienda::TIPO_SUPERAVIT], true)) {
            throw new InvalidArgumentException('Tipo de liquidación no válido');
        }
        foreach (['deuda' => $deudaId, 'contrapartida P' => $contraPId, 'deudores' => $deudoresId, 'contrapartida G' => $contraGId] as $etiqueta => $id) {
            if ($id <= 0) {
                throw new InvalidArgumentException('Falta la cuenta de ' . $etiqueta);
            }
        }

        $p = new Asiento(
            null,
            $ejercicioId,
            'P',
            null,
            $fecha,
            $glosa,
            $tipo,
            'manual',
            $personaId,
            [
                new Movimiento(null, 1, $deudaId, $personaId, $cents, 0),
                new Movimiento(null, 2, $contraPId, null, 0, $cents),
            ],
            null,
            null,
            $fecha,
        );
        $g = new Asiento(
            null,
            $ejercicioId,
            'G',
            null,
            $fecha,
            $glosa,
            $tipo,
            'manual',
            $personaId,
            [
                new Movimiento(null, 1, $contraGId, null, $cents, 0),
                new Movimiento(null, 2, $deudoresId, $personaId, 0, $cents),
            ],
            null,
            null,
            $fecha,
        );

        return [$p, $g];
    }
}
