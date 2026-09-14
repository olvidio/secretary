<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class CambiarPersonaUsuario
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(int $identidadId, int $personaId): int
    {
        $permitidas = $this->identidades->personasDe($identidadId);
        if (!in_array($personaId, $permitidas, true)) {
            throw new InvalidArgumentException('Persona no permitida');
        }

        return $personaId;
    }
}
