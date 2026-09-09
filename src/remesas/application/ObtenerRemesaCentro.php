<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\services\AgregadorRemesaPersonal;
use src\remesas\domain\services\DiffRemesa;

final class ObtenerRemesaCentro
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly RemesaRepository $remesas,
        private readonly PersonaRepository $personas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $id): array
    {
        $ctx = $this->ambito->ejecutar();
        $remesa = $this->remesas->porId($id);
        if ($remesa === null || $remesa->centroId !== $ctx->centroId) {
            throw new InvalidArgumentException('Remesa no encontrada');
        }
        $persona = $this->personas->porId($remesa->personaId);
        $out = $remesa->toArray();
        $out['iniciales'] = $persona?->iniciales;
        $out['persona'] = $persona?->nombreCompleto();
        $lineasOut = [];
        foreach ($remesa->lineas as $linea) {
            $fila = $linea->toArray();
            $fila['nombre'] = AgregadorRemesaPersonal::nombreMaestro($linea->codigoMaestro);
            $sol = $linea->id !== null ? $this->remesas->ultimaSolicitudDeLinea($linea->id) : null;
            $fila['solicitud'] = $sol?->toArray();
            $lineasOut[] = $fila;
        }
        $out['lineas'] = $lineasOut;
        $aceptada = $this->remesas->aceptadaDe(
            $remesa->personaId,
            $remesa->ejercicioId,
            $remesa->anio,
            $remesa->mes,
        );
        $referencia = null;
        if ($aceptada !== null && $aceptada->id !== $remesa->id) {
            $referencia = $aceptada;
        } else {
            $historial = $this->remesas->listarDePersona(
                $remesa->personaId,
                $remesa->ejercicioId,
                $remesa->anio,
                $remesa->mes,
            );
            foreach (array_reverse($historial) as $prev) {
                if ($prev->id !== $remesa->id && $prev->version < $remesa->version) {
                    $referencia = $prev;
                    break;
                }
            }
        }
        $out['diff'] = $referencia !== null ? DiffRemesa::entre($remesa->lineas, $referencia->lineas) : [];
        $out['version_comparada'] = $referencia?->version;

        return $out;
    }
}
