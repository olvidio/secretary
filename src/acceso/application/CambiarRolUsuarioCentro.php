<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\value_objects\RolCentro;

final class CambiarRolUsuarioCentro
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly QuedaEscritorCentro $quedaEscritor,
    ) {
    }

    public function ejecutar(int $centroId, int $identidadId, string $rol): void
    {
        if ($identidadId <= 0) {
            throw new InvalidArgumentException(_("Ese usuario no pertenece a este centro"));
        }
        $rol = RolCentro::exigirAsignable($rol);
        $actual = $this->identidades->rolEnCentro($identidadId, $centroId);
        if ($actual === null) {
            throw new InvalidArgumentException(_("Ese usuario no pertenece a este centro"));
        }
        if ($actual === $rol) {
            return;
        }
        if (RolCentro::puedeEscribir($actual) && !RolCentro::puedeEscribir($rol)) {
            $this->quedaEscritor->comprobar($centroId, $identidadId);
        }
        $this->identidades->vincularCentro($identidadId, $centroId, $rol);
    }
}
