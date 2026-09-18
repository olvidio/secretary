<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\personas\domain\contracts\PersonaRepository;

final class DesvincularPersonaCentro
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
    ) {
    }

    public function ejecutar(int $identidadId, int $personaId): void
    {
        if ($personaId <= 0) {
            throw new InvalidArgumentException(_("Indique la persona"));
        }
        $vinculadas = $this->identidades->personasDe($identidadId);
        if (!in_array($personaId, $vinculadas, true)) {
            throw new InvalidArgumentException(_("No está vinculado a ese centro"));
        }
        $persona = $this->personas->porId($personaId);
        if ($persona === null) {
            throw new InvalidArgumentException(_("Persona no encontrada"));
        }
        $identidad = $this->identidades->porId($identidadId);
        if (
            $identidad !== null
            && $persona->email !== null
            && strtolower($persona->email) === strtolower($identidad->email)
        ) {
            $this->personas->guardarEmail($personaId, null);
        }
        $this->identidades->desvincularPersona($personaId);
    }
}
