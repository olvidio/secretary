<?php

declare(strict_types=1);

namespace src\disponible\application;

use src\ambito\application\ResolverAmbitoActual;
use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\disponible\domain\services\RepartidorLabores;
use src\personas\domain\contracts\PersonaRepository;
use src\plan\domain\contracts\PartidaLaboresRepository;

/** Instrucciones abiertas en el centro (sin apunte en P hasta que llegue por remesa). */
final class ListarInstruccionesDestinos7Pendientes
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly AsignacionLaboresRepository $asignaciones,
        private readonly PersonaRepository $personas,
        private readonly PartidaLaboresRepository $partidas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $etiquetas = [];
        foreach ($this->partidas->paraCentro($ctx->centroId) as $p) {
            $etiquetas[$p['codigo']] = $p['etiqueta'];
        }
        $out = [];
        foreach ($this->asignaciones->instruccionesAbiertasDeCentro($ctx->centroId) as $row) {
            $personaId = (int) $row['persona_id'];
            $persona = $this->personas->porId($personaId);
            $filas = [];
            foreach ($row['lineas'] as $l) {
                $cod = (string) $l['codigo_maestro'];
                $filas[] = [
                    'codigo' => $cod,
                    'etiqueta' => $etiquetas[$cod] ?? $cod,
                    'cents' => (int) $l['importe_cents'],
                ];
            }
            $out[] = [
                'persona_id' => $personaId,
                'iniciales' => $persona !== null ? $persona->iniciales : '',
                'nombre' => $persona !== null ? $persona->nombreCompleto() : '',
                'texto' => RepartidorLabores::texto(array_map(
                    static fn (array $f): array => [
                        'persona_id' => $personaId,
                        'codigo' => $f['codigo'],
                        'etiqueta' => $f['etiqueta'],
                        'cents' => $f['cents'],
                    ],
                    $filas,
                )),
                'lineas' => $filas,
            ];
        }

        return $out;
    }
}
