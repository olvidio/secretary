<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\value_objects\RolCentro;
use src\personas\domain\contracts\PersonaRepository;

/** Nombres del centro que el propio secretario puede enlazar con su cuenta personal. */
final class ListarNombresParaVinculoPropio
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
    ) {
    }

    /**
     * @return list<array{id:int, iniciales:string, nombre:string, ocupado:bool}>
     */
    public function ejecutar(int $identidadId, int $centroId): array
    {
        $this->exigirSecretario($identidadId, $centroId);
        $out = [];
        foreach ($this->personas->listarDeCentro($centroId) as $persona) {
            if ($persona->id === null || !$persona->activo) {
                continue;
            }
            $otra = $this->identidades->identidadDePersona($persona->id);
            $out[] = [
                'id' => $persona->id,
                'iniciales' => $persona->iniciales,
                'nombre' => $persona->nombreCompleto(),
                'ocupado' => $otra !== null && $otra->id !== $identidadId,
            ];
        }

        return $out;
    }

    private function exigirSecretario(int $identidadId, int $centroId): void
    {
        $rol = $this->identidades->rolEnCentro($identidadId, $centroId);
        if ($rol === null || !RolCentro::puedeEscribir($rol)) {
            throw new InvalidArgumentException(_("Solo el secretario de ese centro puede elegir el nombre directamente"));
        }
    }
}
