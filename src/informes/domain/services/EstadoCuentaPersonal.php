<?php

declare(strict_types=1);

namespace src\informes\domain\services;

/**
 * Cómo está la cuenta personal (c/c, concepto 9) de una persona a efectos de
 * comprobaciones. Desde que el sobrante de la remesa ya no se aparca, una c/c
 * positiva con los apuntes A cuadrados es sobrante pendiente de destinar a
 * partidas 7, no un descuadre.
 */
final class EstadoCuentaPersonal
{
    public const OK = 'ok';
    public const POR_DESTINAR = 'por_destinar';
    public const REVISAR = 'revisar';

    public static function de(bool $apuntesACuadrados, int $saldoCents): string
    {
        if ($apuntesACuadrados && $saldoCents === 0) {
            return self::OK;
        }
        if ($apuntesACuadrados && $saldoCents > 0) {
            return self::POR_DESTINAR;
        }

        return self::REVISAR;
    }
}
