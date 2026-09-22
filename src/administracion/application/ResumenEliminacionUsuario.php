<?php

declare(strict_types=1);

namespace src\administracion\application;

use src\acceso\domain\contracts\IdentidadRepository;

final class ResumenEliminacionUsuario
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly ResumenEliminacionCuentaPersonal $personal,
        private readonly ResumenBajaCuentaCentro $centro,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $identidadId): array
    {
        if ($this->identidades->esCuentaPersonal($identidadId)) {
            return $this->personal->ejecutar($identidadId);
        }
        if ($this->identidades->esCuentaSecretarioCentro($identidadId)) {
            return $this->centro->ejecutar($identidadId);
        }

        return $this->personal->ejecutar($identidadId);
    }
}
