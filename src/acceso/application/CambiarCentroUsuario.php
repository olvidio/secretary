<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class CambiarCentroUsuario
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(int $identidadId, int $centroId): int
    {
        foreach ($this->identidades->centrosDe($identidadId) as $v) {
            if ($v->centroId === $centroId) {
                return $centroId;
            }
        }

        throw new InvalidArgumentException(_("Centro no permitido"));
    }
}
