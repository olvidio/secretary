<?php

declare(strict_types=1);

namespace src\mensajes\application;

use src\acceso\domain\contracts\IdentidadRepository;
use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\mensajes\domain\contracts\MensajeRepository;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\services\AgregadorRemesaPersonal;

/** Deja la bandeja alineada con destinos 7 pendientes y solicitudes de detalle. */
final class SincronizarBandeja
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly AsignacionLaboresRepository $asignaciones,
        private readonly PartidaLaboresRepository $partidas,
        private readonly RemesaRepository $remesas,
        private readonly MensajeRepository $mensajes,
    ) {
    }

    public function ejecutar(int $identidadId): void
    {
        $claves = [];
        $personaIds = [];
        $iniciales = [];
        foreach ($this->identidades->personasVinculoDe($identidadId) as $vinculo) {
            $personaId = (int) $vinculo['persona_id'];
            $centroId = (int) $vinculo['centro_id'];
            $personaIds[] = $personaId;
            $iniciales[$personaId] = (string) $vinculo['iniciales'];
            $clave = $this->publicarDestinos(
                $identidadId,
                $centroId,
                $personaId,
                (string) $vinculo['iniciales'],
            );
            if ($clave !== null) {
                $claves[] = $clave;
            }
        }
        foreach ($this->remesas->solicitudesPendientesDePersonas($personaIds) as $solicitud) {
            if ($solicitud->id === null || $solicitud->estado !== 'pendiente') {
                continue;
            }
            $clave = 'detalle:' . $solicitud->id;
            $this->mensajes->upsert(
                $identidadId,
                $clave,
                'remesa_detalle',
                (string) json_encode([
                    'quien' => $iniciales[$solicitud->personaId] ?? '',
                    'codigo' => $solicitud->codigoMaestro,
                    'nombre' => AgregadorRemesaPersonal::nombreMaestro($solicitud->codigoMaestro),
                    'mes' => $solicitud->mes,
                    'anio' => $solicitud->anio,
                    'version' => $solicitud->version,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
            $claves[] = $clave;
        }
        $this->mensajes->cerrarSalvo($identidadId, $claves);
    }

    private function publicarDestinos(int $identidadId, int $centroId, int $personaId, string $iniciales): ?string
    {
        $pendientes = $this->asignaciones->instruccionesPendientesDePersona($centroId, $personaId);
        if ($pendientes === []) {
            return null;
        }
        $etiquetas = [];
        foreach ($this->partidas->paraCentro($centroId) as $partida) {
            $etiquetas[$partida['codigo']] = $partida['etiqueta'];
        }
        $lineas = [];
        foreach ($pendientes as $p) {
            $codigo = (string) $p['codigo_maestro'];
            $lineas[] = [
                'codigo' => $codigo,
                'etiqueta' => $etiquetas[$codigo] ?? $codigo,
                'cents' => (int) $p['importe_cents'],
            ];
        }
        $clave = 'destinos:' . $centroId . ':' . $personaId;
        $this->mensajes->upsert(
            $identidadId,
            $clave,
            'destinos_7',
            (string) json_encode([
                'quien' => $iniciales,
                'lineas' => $lineas,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        return $clave;
    }
}
