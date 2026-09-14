<?php

declare(strict_types=1);

namespace src\acceso\application;

use src\acceso\domain\contracts\IdentidadRepository;

final class ResolverPersonaActiva
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    /**
     * @return array{persona_id: ?int, requiere_elegir: bool}
     */
    public function ejecutar(int $identidadId, ?int $personaSesion): array
    {
        $personas = $this->identidades->personasDe($identidadId);
        if ($personas === []) {
            return ['persona_id' => null, 'requiere_elegir' => false];
        }
        if (count($personas) === 1) {
            return ['persona_id' => $personas[0], 'requiere_elegir' => false];
        }
        if ($personaSesion !== null && $personaSesion > 0 && in_array($personaSesion, $personas, true)) {
            return ['persona_id' => $personaSesion, 'requiere_elegir' => false];
        }

        return ['persona_id' => null, 'requiere_elegir' => true];
    }
}
