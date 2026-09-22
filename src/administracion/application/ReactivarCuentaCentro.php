<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class ReactivarCuentaCentro
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(int $identidadId): void
    {
        if ($this->identidades->bajaCentroPendiente($identidadId) === null) {
            throw new InvalidArgumentException(_("Esta cuenta no está en baja programada"));
        }
        $respaldo = $this->identidades->respaldoCentrosBaja($identidadId);
        if ($respaldo === []) {
            throw new InvalidArgumentException(_("No hay datos de reactivación para esta cuenta"));
        }

        $this->identidades->cancelarBajaCentro($identidadId);
        foreach ($respaldo as $fila) {
            if ($fila['centro_id'] <= 0) {
                continue;
            }
            $this->identidades->vincularCentro($identidadId, $fila['centro_id'], $fila['rol']);
        }
        $this->identidades->eliminarRespaldoCentrosBaja($identidadId);
    }
}
