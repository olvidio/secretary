<?php

declare(strict_types=1);

namespace src\remesas\application;

use RuntimeException;
use src\acceso\domain\contracts\IdentidadRepository;

/** Personas cuya bandeja de remesas pertenece a la identidad (libro propio + vínculos). */
final class PersonasRemesaDeIdentidad
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly ?int $identidadId,
    ) {
    }

    /** @return list<int> */
    public function ejecutar(): array
    {
        if ($this->identidadId === null) {
            throw new RuntimeException(_("Sesión de persona incompleta"));
        }

        return $this->identidades->personasDe($this->identidadId);
    }
}
