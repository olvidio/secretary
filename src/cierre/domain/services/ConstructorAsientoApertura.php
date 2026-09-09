<?php

declare(strict_types=1);

namespace src\cierre\domain\services;

use InvalidArgumentException;
use src\asientos\domain\entity\Movimiento;

/**
 * Construye los movimientos de un asiento de apertura (D12) a partir de saldos
 * de cierre. Cuadre estricto (D1): la contrapartida de patrimonio cierra la
 * diferencia sin compensar errores del ejercicio anterior.
 */
final class ConstructorAsientoApertura
{
    /**
     * @param list<array{id:int, libro:string, codigo:string, codigo_maestro:string, tipo:string, persona_id:?int, cuenta_fisica_id:?int, saldo_cents:int}> $saldos
     * @return list<Movimiento>
     */
    public static function movimientos(string $libro, array $saldos, int $cuentaPatrimonioId): array
    {
        if (!in_array($libro, ['P', 'G'], true)) {
            throw new InvalidArgumentException('Libro no válido para apertura: ' . $libro);
        }

        $movimientos = [];
        $orden = 1;
        foreach ($saldos as $row) {
            if (!self::debeArrastrar($libro, $row)) {
                continue;
            }
            $saldo = (int) $row['saldo_cents'];
            if ($saldo === 0) {
                continue;
            }
            $personaId = $row['persona_id'] !== null ? (int) $row['persona_id'] : null;
            if ($saldo > 0) {
                $movimientos[] = new Movimiento(null, $orden++, (int) $row['id'], $personaId, $saldo, 0);
            } else {
                $movimientos[] = new Movimiento(null, $orden++, (int) $row['id'], $personaId, 0, abs($saldo));
            }
        }

        if ($movimientos === []) {
            return [];
        }

        $debe = 0;
        $haber = 0;
        foreach ($movimientos as $mov) {
            $debe += $mov->debeCents;
            $haber += $mov->haberCents;
        }
        $plug = $debe - $haber;
        if ($plug !== 0) {
            if ($plug > 0) {
                $movimientos[] = new Movimiento(null, $orden, $cuentaPatrimonioId, null, 0, $plug);
            } else {
                $movimientos[] = new Movimiento(null, $orden, $cuentaPatrimonioId, null, abs($plug), 0);
            }
        }

        if (count($movimientos) < 2) {
            return [];
        }

        $debe = 0;
        $haber = 0;
        foreach ($movimientos as $mov) {
            $debe += $mov->debeCents;
            $haber += $mov->haberCents;
        }
        if ($debe !== $haber) {
            throw new InvalidArgumentException(
                sprintf('La apertura del libro %s no cuadra (debe=%d, haber=%d)', $libro, $debe, $haber)
            );
        }

        return $movimientos;
    }

    /**
     * @param array{id:int, libro:string, codigo:string, codigo_maestro:string, tipo:string, persona_id:?int, cuenta_fisica_id:?int, saldo_cents:int} $row
     */
    private static function debeArrastrar(string $libro, array $row): bool
    {
        if ($row['libro'] !== $libro) {
            return false;
        }

        return match ($row['tipo']) {
            'tesoreria', 'personal' => true,
            'puente' => in_array($row['codigo'], ['PUENTE.LIBROS', 'PUENTE.PERIODIFICACION'], true),
            default => false,
        };
    }
}
