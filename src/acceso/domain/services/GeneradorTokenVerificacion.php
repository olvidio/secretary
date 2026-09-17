<?php

declare(strict_types=1);

namespace src\acceso\domain\services;

final class GeneradorTokenVerificacion
{
    public static function generar(): string
    {
        return bin2hex(random_bytes(32));
    }
}
