<?php

declare(strict_types=1);

namespace src\personas\application;

use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\CentroRepository;
use src\personas\domain\contracts\SolicitudVinculoCentroRepository;

final class ListarSolicitudesVinculoCentro
{
    public function __construct(
        private readonly SolicitudVinculoCentroRepository $solicitudes,
        private readonly IdentidadRepository $identidades,
        private readonly CentroRepository $centros,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(int $centroId): array
    {
        $out = [];
        foreach ($this->solicitudes->pendientesDeCentro($centroId) as $s) {
            $identidad = $this->identidades->porId($s->identidadId);
            $centro = $this->centros->porId($s->centroId);
            $fila = $s->toArray();
            $fila['centro_nombre'] = $centro?->nombre ?? '';
            $fila['solicitante_nombre'] = $identidad?->nombre ?? '';
            $fila['solicitante_email'] = $identidad?->email ?? '';
            $fila['solicitante_alias'] = $identidad?->alias ?? '';
            $out[] = $fila;
        }

        return $out;
    }
}
