<?php

declare(strict_types=1);

namespace src\cierre\domain\services;

/**
 * Lo que se puede aplicar a la deuda: primero el superávit de vivienda,
 * y el resto con el saldo contable positivo, sin pasar de lo adeudado.
 */
final class ImporteSaldableDeuda
{
    /**
     * @return array{superavit: int, saldo: int, total: int}
     */
    public static function partes(int $deudaSaldoCents, int $superavitCents, int $saldoContableCents): array
    {
        $deuda = $deudaSaldoCents < 0 ? -$deudaSaldoCents : 0;
        $super = $superavitCents > 0 ? $superavitCents : 0;
        $cc = $saldoContableCents > 0 ? $saldoContableCents : 0;
        $desdeSuperavit = min($deuda, $super);
        $desdeSaldo = min($deuda - $desdeSuperavit, $cc);

        return [
            'superavit' => $desdeSuperavit,
            'saldo' => $desdeSaldo,
            'total' => $desdeSuperavit + $desdeSaldo,
        ];
    }
}
