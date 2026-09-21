<?php

declare(strict_types=1);

namespace src\apuntes\domain\services;

use InvalidArgumentException;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

/** Asiento de dos patas del libro G (concepto contra banco del centro). */
final class ConstructorAsientoCentroBanco
{
    public static function movimiento(
        int $ejercicioId,
        \DateTimeImmutable $fecha,
        ?string $glosa,
        string $sentido,
        int $conceptoId,
        int $tesoreriaId,
        int $cents,
        ?string $conceptoCodigo,
    ): Asiento {
        if ($cents <= 0) {
            throw new InvalidArgumentException(_('La cantidad debe ser positiva'));
        }
        if ($sentido === 'ingreso') {
            $movimientos = [
                new Movimiento(null, 1, $tesoreriaId, null, $cents, 0),
                new Movimiento(null, 2, $conceptoId, null, 0, $cents),
            ];
        } elseif ($sentido === 'gasto') {
            $movimientos = [
                new Movimiento(null, 1, $conceptoId, null, $cents, 0),
                new Movimiento(null, 2, $tesoreriaId, null, 0, $cents),
            ];
        } else {
            throw new InvalidArgumentException(_('Sentido ingreso o gasto'));
        }

        return new Asiento(
            null,
            $ejercicioId,
            'G',
            null,
            $fecha,
            $glosa,
            'normal',
            'banco',
            null,
            $movimientos,
            $conceptoCodigo,
            null,
            $fecha,
        );
    }
}
