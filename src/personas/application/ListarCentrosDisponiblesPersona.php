<?php

declare(strict_types=1);

namespace src\personas\application;

use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\CentroRepository;

final class ListarCentrosDisponiblesPersona
{
    public function __construct(
        private readonly CentroRepository $centros,
        private readonly IdentidadRepository $identidades,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(int $identidadId): array
    {
        $out = [];
        foreach ($this->centros->listar() as $centro) {
            if ($centro->id === null || !$centro->activo) {
                continue;
            }
            if ($this->identidades->tienePersonaEnCentro($identidadId, $centro->id)) {
                continue;
            }
            $out[] = $centro->toArray();
        }

        return $out;
    }
}
