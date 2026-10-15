<?php

declare(strict_types=1);

namespace src\cierre\domain\services;

/** Exceso de lo ingresado en vivienda (sin el cierre automático) sobre la cuota del ejercicio. */
final class SuperavitVivienda
{
    public static function cents(int $ingresadoCents, int $objetivoCents): int
    {
        $exceso = $ingresadoCents - $objetivoCents;

        return $exceso > 0 ? $exceso : 0;
    }

    /**
     * Saldo de deuda si se deshacen las aplicaciones de superávit ya anotadas.
     * El saldo es debe − haber; la aplicación abona (debe) la deuda.
     */
    public static function deudaSinAplicar(int $deudaSaldoCents, int $aplicadoCents): int
    {
        $aplicado = $aplicadoCents > 0 ? $aplicadoCents : 0;

        return $deudaSaldoCents - $aplicado;
    }
}
