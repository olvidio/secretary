<?php

declare(strict_types=1);

namespace src\acceso\application;

use src\acceso\domain\entity\Identidad;
use src\acceso\domain\value_objects\RolCentro;

/** Delega en {@see InvitarUsuarioCentro} (D15). */
final class AsegurarIdentidadCentro
{
    public function __construct(private readonly InvitarUsuarioCentro $invitar)
    {
    }

    public function ejecutar(
        int $centroId,
        string $alias,
        string $email,
        string $password,
        string $nombre = '',
        string $rol = RolCentro::ADMIN,
        bool $verificarEmail = true,
    ): Identidad {
        return $this->invitar->ejecutar(
            $centroId,
            $email,
            $alias,
            $password,
            $nombre,
            $rol,
            $verificarEmail,
        );
    }
}
