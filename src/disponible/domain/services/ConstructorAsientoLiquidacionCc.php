<?php

declare(strict_types=1);

namespace src\disponible\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

/** Baja el saldo CC (concepto 9) al confirmar destinos 7: Dr 111 / Cr CC. */
final class ConstructorAsientoLiquidacionCc
{
    public static function construir(
        int $ejercicioId,
        int $personaId,
        DateTimeImmutable $fecha,
        int $cuenta111Id,
        int $ccId,
        int $totalCents,
        string $glosa,
    ): ?Asiento {
        if ($totalCents <= 0) {
            return null;
        }
        if ($cuenta111Id <= 0 || $ccId <= 0) {
            throw new InvalidArgumentException('Faltan las cuentas 111 o CC');
        }

        return new Asiento(
            null,
            $ejercicioId,
            'P',
            null,
            $fecha,
            $glosa,
            'asignacion_cc',
            'asignacion',
            $personaId,
            [
                new Movimiento(null, 1, $cuenta111Id, $personaId, $totalCents, 0),
                new Movimiento(null, 2, $ccId, $personaId, 0, $totalCents),
            ],
            null,
            null,
            $fecha,
            null,
        );
    }
}
