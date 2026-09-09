<?php

declare(strict_types=1);

namespace src\personal\domain\services;

use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

/** Arma el asiento de dos patas del nivel 1 (categoría contra caja/banco). */
final class ConstructorAsientoPersonal
{
    public static function movimiento(
        int $ejercicioId,
        int $personaId,
        \DateTimeImmutable $fecha,
        ?string $glosa,
        string $sentido,
        int $categoriaId,
        int $tesoreriaId,
        int $cents,
        ?string $conceptoCodigo,
        ?\DateTimeImmutable $fechaOperacion = null,
    ): Asiento {
        if ($cents <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser positiva');
        }
        if ($sentido === 'ingreso') {
            $movimientos = [
                new Movimiento(null, 1, $tesoreriaId, $personaId, $cents, 0),
                new Movimiento(null, 2, $categoriaId, $personaId, 0, $cents),
            ];
        } elseif ($sentido === 'gasto') {
            $movimientos = [
                new Movimiento(null, 1, $categoriaId, $personaId, $cents, 0),
                new Movimiento(null, 2, $tesoreriaId, $personaId, 0, $cents),
            ];
        } else {
            throw new \InvalidArgumentException('Sentido ingreso o gasto');
        }

        return new Asiento(
            null,
            $ejercicioId,
            'X',
            null,
            $fecha,
            $glosa,
            'normal',
            'manual',
            $personaId,
            $movimientos,
            $conceptoCodigo,
            null,
            $fechaOperacion,
        );
    }

    public static function traspaso(
        int $ejercicioId,
        int $personaId,
        \DateTimeImmutable $fecha,
        ?string $glosa,
        int $origenId,
        int $destinoId,
        int $cents,
    ): Asiento {
        if ($cents <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser positiva');
        }
        if ($origenId === $destinoId) {
            throw new \InvalidArgumentException('Origen y destino no pueden ser la misma tesorería');
        }

        return new Asiento(
            null,
            $ejercicioId,
            'X',
            null,
            $fecha,
            $glosa,
            'traspaso',
            'manual',
            $personaId,
            [
                new Movimiento(null, 1, $destinoId, $personaId, $cents, 0),
                new Movimiento(null, 2, $origenId, $personaId, 0, $cents),
            ],
        );
    }
}
