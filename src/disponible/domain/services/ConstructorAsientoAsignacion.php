<?php

declare(strict_types=1);

namespace src\disponible\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

/**
 * Asiento P al confirmar la propuesta: gastos 7 contra 111 (apuntes A cuadrados).
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
        int $cuenta111Id,
        array $lineas,
        string $glosa,
    ): ?Asiento {
        if ($cuenta111Id <= 0) {
            throw new InvalidArgumentException('Falta la cuenta 111 del plan P');
        }
        $movimientos = [];
        $orden = 1;
        $haber111 = 0;
        foreach ($lineas as $linea) {
            $cents = (int) $linea['importe_cents'];
            if ($cents <= 0) {
                continue;
            }
            $movimientos[] = new Movimiento(null, $orden++, (int) $linea['cuenta_id'], $personaId, $cents, 0);
            $haber111 += $cents;
        }
        if ($haber111 === 0) {
            return null;
        }
        $movimientos[] = new Movimiento(null, $orden, $cuenta111Id, $personaId, 0, $haber111);

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
