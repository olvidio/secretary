<?php

declare(strict_types=1);

namespace src\personas\application;

use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\CentroRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\contracts\SolicitudVinculoCentroRepository;

final class ListarVinculosPersona
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
        private readonly CentroRepository $centros,
        private readonly SolicitudVinculoCentroRepository $solicitudes,
    ) {
    }

    /** @return array{vinculos: list<array<string, mixed>>, solicitudes: list<array<string, mixed>>} */
    public function ejecutar(int $identidadId): array
    {
        $vinculos = [];
        foreach ($this->identidades->personasDe($identidadId) as $personaId) {
            $persona = $this->personas->porId($personaId);
            if ($persona === null || $persona->centroId === null) {
                continue;
            }
            $centro = $this->centros->porId($persona->centroId);
            $fila = $persona->toArray();
            $fila['centro_id'] = $persona->centroId;
            $fila['centro_nombre'] = $centro?->nombre ?? '';
            $fila['centro_codigo'] = $centro?->codigo ?? '';
            $fila['anio'] = $this->identidades->anioVinculoPersona($identidadId, $personaId);
            $vinculos[] = $fila;
        }

        $identidad = $this->identidades->porId($identidadId);
        $solicitudes = [];
        foreach ($this->solicitudes->deIdentidad($identidadId) as $s) {
            $centro = $this->centros->porId($s->centroId);
            $fila = $s->toArray();
            $fila['centro_nombre'] = $centro?->nombre ?? '';
            $fila['centro_codigo'] = $centro?->codigo ?? '';
            $fila['solicitante_nombre'] = $identidad?->nombre ?? '';
            $fila['solicitante_email'] = $identidad?->email ?? '';
            $solicitudes[] = $fila;
        }

        return ['vinculos' => $vinculos, 'solicitudes' => $solicitudes];
    }
}
