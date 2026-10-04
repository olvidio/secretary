<?php

declare(strict_types=1);

namespace src\acceso\domain\value_objects;

use InvalidArgumentException;

/** Rol del vínculo identidad–centro. `operador` es el valor antiguo y escribe. */
final class RolCentro
{
    public const ADMIN = 'admin';

    public const OPERADOR = 'operador';

    public const CONSULTA = 'consulta';

    public static function exigirAsignable(string $rol): string
    {
        $rol = strtolower(trim($rol));
        if ($rol === self::ADMIN || $rol === self::CONSULTA) {
            return $rol;
        }

        throw new InvalidArgumentException(_("Rol no válido"));
    }

    public static function esConsulta(?string $rol): bool
    {
        return strtolower(trim((string) $rol)) === self::CONSULTA;
    }

    public static function puedeEscribir(?string $rol): bool
    {
        return !self::esConsulta($rol);
    }
}
