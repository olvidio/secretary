<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use RuntimeException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\personal\domain\value_objects\ContextoPersonal;
use src\personas\domain\contracts\PersonaRepository;

final class ResolverPersonaActual
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsegurarPlanPersonal $asegurar,
        private readonly ?int $identidadId,
        private readonly ?int $personaId,
    ) {
    }

    public function ejecutar(): ContextoPersonal
    {
        if ($this->identidadId === null || $this->personaId === null) {
            throw new RuntimeException('Sesión de persona incompleta');
        }
        $vinculos = $this->identidades->personasDe($this->identidadId);
        if (!in_array($this->personaId, $vinculos, true)) {
            throw new InvalidArgumentException('Esa persona no pertenece a esta identidad');
        }
        $persona = $this->personas->porId($this->personaId);
        if ($persona === null || $persona->centroId === null) {
            throw new InvalidArgumentException(
                'La persona no está vinculada a un centro; no se puede abrir el libro personal'
            );
        }
        $ejercicio = $this->ejercicios->abiertoDe($persona->centroId);
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new RuntimeException('El centro no tiene ningún ejercicio abierto');
        }
        $this->asegurar->ejecutar($persona->centroId, $persona->id ?? $this->personaId);

        return new ContextoPersonal($persona->centroId, $ejercicio->id, $this->personaId);
    }
}
