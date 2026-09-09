<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\services\AgregadorRemesaPersonal;

final class ObtenerDetalleRemesa
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly RemesaRepository $remesas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $remesaId, int $lineaId): array
    {
        $ctx = $this->ambito->ejecutar();
        $remesa = $this->remesas->porId($remesaId);
        if ($remesa === null || $remesa->centroId !== $ctx->centroId) {
            throw new InvalidArgumentException('Remesa no encontrada');
        }
        $linea = null;
        foreach ($remesa->lineas as $l) {
            if ($l->id === $lineaId) {
                $linea = $l;
                break;
            }
        }
        if ($linea === null) {
            throw new InvalidArgumentException('Línea no encontrada');
        }
        $solicitud = $this->remesas->ultimaSolicitudDeLinea($lineaId);
        if ($solicitud === null || $solicitud->estado !== 'autorizada') {
            throw new InvalidArgumentException('El detalle no está autorizado');
        }
        $fila = $linea->toArray();
        $fila['nombre'] = AgregadorRemesaPersonal::nombreMaestro($linea->codigoMaestro);
        $fila['solicitud'] = $solicitud->toArray();

        return $fila;
    }
}
