<?php

declare(strict_types=1);

namespace src\acceso\domain\services;

use src\shared\infrastructure\persistence\ConnectionFactory;

final class PlazoBajaCentro
{
    public static function dias(): int
    {
        $raw = ConnectionFactory::env('BAJA_CENTRO_DIAS', '60') ?? '60';
        $dias = (int) $raw;

        return $dias >= 1 ? min($dias, 365) : 60;
    }
}
