<?php

declare(strict_types=1);

namespace src\disponible\application;

use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\disponible\domain\services\RepartidorLabores;
use src\personal\application\ResolverPersonaActual;
use src\plan\domain\contracts\PartidaLaboresRepository;

final class ListarAsignacionesPersona
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly AsignacionLaboresRepository $asignaciones,
        private readonly PartidaLaboresRepository $partidas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $etiquetas = [];
        foreach ($this->partidas->paraCentro($ctx->centroId) as $p) {
            $etiquetas[$p['codigo']] = $p['etiqueta'];
        }
        $pendientes = $this->asignaciones->instruccionesPendientesDePersona($ctx->centroId, $ctx->personaId);
        $filas = [];
        foreach ($pendientes as $p) {
            $filas[] = [
                'persona_id' => $ctx->personaId,
                'codigo' => $p['codigo_maestro'],
                'etiqueta' => $etiquetas[$p['codigo_maestro']] ?? $p['codigo_maestro'],
                'cents' => $p['importe_cents'],
            ];
        }

        return [
            'lineas' => $pendientes,
            'texto' => RepartidorLabores::texto($filas),
        ];
    }
}
