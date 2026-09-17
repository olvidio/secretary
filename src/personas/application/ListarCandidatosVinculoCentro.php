<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\contracts\SolicitudVinculoCentroRepository;

final class ListarCandidatosVinculoCentro
{
    public function __construct(
        private readonly SolicitudVinculoCentroRepository $solicitudes,
        private readonly PersonaRepository $personas,
        private readonly IdentidadRepository $identidades,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(int $centroId, int $solicitudId): array
    {
        $solicitud = $this->solicitudes->porId($solicitudId);
        if ($solicitud === null || $solicitud->centroId !== $centroId || !$solicitud->esPendiente()) {
            throw new InvalidArgumentException(_("Solicitud no encontrada"));
        }
        $identidad = $this->identidades->porId($solicitud->identidadId);
        $termino = $identidad?->nombre ?? '';
        if ($termino === '' && $identidad?->alias !== null) {
            $termino = $identidad->alias;
        }

        $out = [];
        foreach ($this->personas->buscarPorNombreEnCentro($centroId, $termino) as $p) {
            if ($p->id === null) {
                continue;
            }
            $vinculada = $this->identidades->identidadDePersona($p->id);
            $fila = $p->toArray();
            $fila['email'] = $p->email ?? '';
            $fila['tiene_cuenta'] = $vinculada !== null;
            $fila['cuenta_es_solicitante'] = $vinculada !== null && $vinculada->id === $solicitud->identidadId;
            $out[] = $fila;
        }

        return $out;
    }
}
