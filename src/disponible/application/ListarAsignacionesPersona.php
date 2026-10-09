<?php

declare(strict_types=1);

namespace src\disponible\application;

use RuntimeException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\disponible\domain\services\RepartidorLabores;
use src\plan\domain\contracts\PartidaLaboresRepository;

/**
 * Texto «Deberías ingresar…» de las labores confirmadas.
 * Las asignaciones van contra la persona del centro (no contra el libro personal tipo p).
 */
final class ListarAsignacionesPersona
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly AsignacionLaboresRepository $asignaciones,
        private readonly PartidaLaboresRepository $partidas,
        private readonly ?int $identidadId,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(): array
    {
        if ($this->identidadId === null) {
            throw new RuntimeException(_("Sesión de persona incompleta"));
        }
        $filas = [];
        $lineas = [];
        $etiquetasPorCentro = [];
        foreach ($this->identidades->personasVinculoDe($this->identidadId) as $vinculo) {
            $centroId = (int) $vinculo['centro_id'];
            $personaId = (int) $vinculo['persona_id'];
            if (!isset($etiquetasPorCentro[$centroId])) {
                $etiquetasPorCentro[$centroId] = [];
                foreach ($this->partidas->paraCentro($centroId) as $p) {
                    $etiquetasPorCentro[$centroId][$p['codigo']] = $p['etiqueta'];
                }
            }
            $pendientes = $this->asignaciones->instruccionesPendientesDePersona($centroId, $personaId);
            foreach ($pendientes as $p) {
                $lineas[] = $p;
                $codigo = (string) $p['codigo_maestro'];
                $filas[] = [
                    'persona_id' => $personaId,
                    'codigo' => $codigo,
                    'etiqueta' => $etiquetasPorCentro[$centroId][$codigo] ?? $codigo,
                    'cents' => (int) $p['importe_cents'],
                ];
            }
        }

        return [
            'lineas' => $lineas,
            'texto' => RepartidorLabores::texto($filas),
        ];
    }
}
