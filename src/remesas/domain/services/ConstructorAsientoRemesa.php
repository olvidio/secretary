<?php

declare(strict_types=1);

namespace src\remesas\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

/**
 * Un asiento P del centro por remesa aceptada: conceptos contra CC (como origen A).
 *
 * @phpstan-type LineaAsiento array{cuenta_id:int, tipo:string, importe_cents:int}
 */
final class ConstructorAsientoRemesa
{
    /**
     * @param list<LineaAsiento> $lineas
     */
    public static function construir(
        int $ejercicioId,
        int $personaId,
        DateTimeImmutable $fecha,
        ?string $glosa,
        int $remesaId,
        int $ccId,
        array $lineas,
    ): ?Asiento {
        if ($remesaId <= 0) {
            throw new InvalidArgumentException('Falta el identificador de la remesa');
        }
        if ($ccId <= 0) {
            throw new InvalidArgumentException('Falta la cuenta personal del centro');
        }
        $movimientos = [];
        $orden = 1;
        $ccDebe = 0;
        $ccHaber = 0;
        foreach ($lineas as $linea) {
            $cents = $linea['importe_cents'];
            $tipo = $linea['tipo'];
            if ($cents === 0) {
                continue;
            }
            if ($cents < 0) {
                $cents = -$cents;
                $tipo = $tipo === 'gasto' ? 'ingreso' : 'gasto';
            }
            $cuentaId = $linea['cuenta_id'];
            if ($tipo === 'gasto') {
                $movimientos[] = new Movimiento(null, $orden++, $cuentaId, $personaId, $cents, 0);
                $ccHaber += $cents;
            } elseif ($tipo === 'ingreso') {
                $movimientos[] = new Movimiento(null, $orden++, $cuentaId, $personaId, 0, $cents);
                $ccDebe += $cents;
            } else {
                throw new InvalidArgumentException('Tipo de línea de remesa no válido: ' . $tipo);
            }
        }
        $neto = $ccDebe - $ccHaber;
        if ($neto > 0) {
            $movimientos[] = new Movimiento(null, $orden++, $ccId, $personaId, $neto, 0);
        } elseif ($neto < 0) {
            $movimientos[] = new Movimiento(null, $orden++, $ccId, $personaId, 0, -$neto);
        }
        if (count($movimientos) < 2) {
            return null;
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
