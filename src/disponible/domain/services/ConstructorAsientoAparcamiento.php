<?php

declare(strict_types=1);

namespace src\disponible\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

/** Mueve el sobrante de la remesa de CC a DISP para dejar la c/c a cero. */
final class ConstructorAsientoAparcamiento
{
    public static function construir(
        int $ejercicioId,
        int $personaId,
        DateTimeImmutable $fecha,
        int $remesaId,
        int $ccId,
        int $dispId,
        int $sobranteCents,
        string $glosa,
    ): ?Asiento {
        if ($sobranteCents === 0) {
            return null;
        }
        if ($ccId <= 0 || $dispId <= 0) {
            throw new InvalidArgumentException('Faltan las cuentas CC o DISP');
        }
        $abs = abs($sobranteCents);
        if ($sobranteCents > 0) {
            $movimientos = [
                new Movimiento(null, 1, $dispId, $personaId, $abs, 0),
                new Movimiento(null, 2, $ccId, $personaId, 0, $abs),
            ];
        } else {
            $movimientos = [
                new Movimiento(null, 1, $ccId, $personaId, $abs, 0),
                new Movimiento(null, 2, $dispId, $personaId, 0, $abs),
            ];
        }

        return new Asiento(
            null,
            $ejercicioId,
            'P',
            null,
            $fecha,
            $glosa,
            'remesa',
            'remesa',
            $personaId,
            $movimientos,
            null,
            null,
            $fecha,
            $remesaId,
        );
    }
}
