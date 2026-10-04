<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\value_objects\RolCentro;

/** Un centro no puede quedarse sin nadie que pueda modificar sus datos. */
final class QuedaEscritorCentro
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function comprobar(int $centroId, int $exceptoIdentidadId): void
    {
        foreach ($this->identidades->usuariosDeCentro($centroId) as $usuario) {
            if ($usuario['id'] === $exceptoIdentidadId) {
                continue;
            }
            if (RolCentro::puedeEscribir($usuario['rol'])) {
                return;
            }
        }

        throw new InvalidArgumentException(_("Debe quedar al menos un usuario que pueda modificar"));
    }
}
