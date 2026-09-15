<?php

declare(strict_types=1);

namespace src\disponible\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

/**
 * Asiento P al confirmar la propuesta: gastos 7 contra DISP.
 *
 * @phpstan-type Linea array{cuenta_id:int, importe_cents:int}
 */
final class ConstructorAsientoAsignacion
{
    /**
     * @param list<Linea> $lineas
     */
    public static function construir(
        int $ejercicioId,
        int $personaId,
        DateTimeImmutable $fecha,
        int $dispId,
        array $lineas,
        string $glosa,
    ): ?Asiento {
        if ($dispId <= 0) {
            throw new InvalidArgumentException('Falta la cuenta DISP');
        }
        $movimientos = [];
        $orden = 1;
        $haberDisp = 0;
        foreach ($lineas as $linea) {
            $cents = (int) $linea['importe_cents'];
            if ($cents <= 0) {
                continue;
            }
            $movimientos[] = new Movimiento(null, $orden++, (int) $linea['cuenta_id'], $personaId, $cents, 0);
            $haberDisp += $cents;
        }
        if ($haberDisp === 0) {
            return null;
        }
        $movimientos[] = new Movimiento(null, $orden, $dispId, $personaId, 0, $haberDisp);

        return new Asiento(
            null,
            $ejercicioId,
            'P',
            null,
            $fecha,
            $glosa,
            'normal',
            'asignacion',
            $personaId,
            $movimientos,
            null,
            null,
            $fecha,
            null,
        );
    }
}
