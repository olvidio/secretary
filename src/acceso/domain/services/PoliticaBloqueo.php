<?php

declare(strict_types=1);

namespace src\acceso\domain\services;

use DateTimeImmutable;

final class PoliticaBloqueo
{
    public const MAX_INTENTOS = 5;
    public const MINUTOS = 15;

    public static function estaBloqueada(?DateTimeImmutable $hasta, DateTimeImmutable $ahora): bool
    {
        return $hasta !== null && $hasta > $ahora;
    }

    public static function bloqueadoHasta(DateTimeImmutable $ahora): DateTimeImmutable
    {
        return $ahora->modify('+' . self::MINUTOS . ' minutes');
    }

    public static function debeBloquear(int $intentosTrasFallo): bool
    {
        return $intentosTrasFallo >= self::MAX_INTENTOS;
    }
}
