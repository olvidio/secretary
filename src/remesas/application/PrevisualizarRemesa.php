<?php

declare(strict_types=1);

namespace src\remesas\application;

use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\Remesa;
use src\remesas\domain\services\AgregadorRemesaPersonal;
use src\remesas\domain\services\DiffRemesa;

final class PrevisualizarRemesa
{
    public function __construct(
        private readonly ResolverMesRemesa $mes,
        private readonly RemesaRepository $remesas,
        private readonly PersonaRepository $personas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $anio, int $mes): array
    {
        $datos = $this->mes->ejecutar($anio, $mes);
        $ctx = $datos['ctx'];
        $ejercicio = $datos['ejercicio'];
        $lineas = $datos['lineas'];
        $historial = $this->remesas->listarDePersona($ctx->personaId, (int) $ejercicio->id, $anio, $mes);
        $enviada = $this->remesas->enviadaDe($ctx->personaId, (int) $ejercicio->id, $anio, $mes);
        $aceptada = $this->remesas->aceptadaDe($ctx->personaId, (int) $ejercicio->id, $anio, $mes);
        $referencia = $aceptada ?? $enviada;
        $diff = $referencia !== null ? DiffRemesa::entre($lineas, $referencia->lineas) : [];
        $abierto = $ejercicio->estado === 'abierto';
        $motivo = $abierto ? null : 'El ejercicio de ese mes está cerrado';
        $persona = $this->personas->porId($ctx->personaId);

        return [
            'anio' => $anio,
            'mes' => $mes,
            'ejercicio_id' => $ejercicio->id,
            'ejercicio_abierto' => $abierto,
            'persona' => $persona?->nombreCompleto(),
            'iniciales' => $persona?->iniciales,
            'lineas' => $this->lineasConNombre($lineas),
            'vacia' => $lineas === [],
            'historial' => array_map(static fn (Remesa $r) => $r->toArray(), $historial),
            'enviada' => $enviada?->toArray(),
            'aceptada' => $aceptada?->toArray(),
            'diff' => $diff,
            'puede_enviar' => $abierto,
            'motivo' => $motivo,
        ];
    }

    /**
     * @param list<\src\remesas\domain\entity\RemesaLinea> $lineas
     * @return list<array<string, mixed>>
     */
    private function lineasConNombre(array $lineas): array
    {
        $out = [];
        foreach ($lineas as $linea) {
            $fila = $linea->toArray();
            $fila['nombre'] = AgregadorRemesaPersonal::nombreMaestro($linea->codigoMaestro);
            $out[] = $fila;
        }

        return $out;
    }
}
