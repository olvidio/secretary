<?php

declare(strict_types=1);

namespace src\acceso\domain\services;

/** Huella del token de restablecer contraseña. No usa APP_KEY: el repositorio sigue siendo comprobable sin secreto. */
final class HuellaToken
{
    public static function de(string $token): string
    {
        return hash('sha256', $token);
    }
}
