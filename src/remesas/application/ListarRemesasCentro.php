<?php

declare(strict_types=1);

namespace src\remesas\application;

use src\ambito\application\ResolverAmbitoActual;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\Remesa;
use src\remesas\domain\services\AgregadorRemesaPersonal;

final class ListarRemesasCentro
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly RemesaRepository $remesas,
        private readonly PersonaRepository $personas,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ejecutar(?string $estado = null): array
    {
        $ctx = $this->ambito->ejecutar();
        $nombres = [];
        foreach ($this->personas->listarDeCentro($ctx->centroId) as $p) {
            if ($p->id !== null) {
                $nombres[$p->id] = $p;
            }
        }
        $out = [];
        foreach ($this->remesas->listarDeCentro($ctx->centroId, $estado) as $remesa) {
            $out[] = $this->fila($remesa, $nombres);
        }

        return $out;
    }

    /**
     * @param array<int, \src\personas\domain\entity\Persona> $nombres
     * @return array<string, mixed>
     */
    private function fila(Remesa $remesa, array $nombres): array
    {
        $persona = $nombres[$remesa->personaId] ?? null;
        $fila = $remesa->toArray();
        $fila['iniciales'] = $persona?->iniciales;
        $fila['persona'] = $persona?->nombreCompleto();
        foreach ($fila['lineas'] as $i => $linea) {
            $fila['lineas'][$i]['nombre'] = AgregadorRemesaPersonal::nombreMaestro(
                (string) $linea['codigo_maestro']
            );
        }

        return $fila;
    }
}
