<?php

declare(strict_types=1);

namespace src\remesas\domain\services;

/** Lo que viaja en la remesa: saldo de caja y banco menos el remanente que se queda. */
final class CalculoDisponibleRemesa
{
    public static function cents(int $saldoCents, int $remanenteCents): int
    {
        $reserva = max(0, $remanenteCents);

        return max(0, $saldoCents - $reserva);
    }
}
