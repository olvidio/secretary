<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\personal\application\ResolverPersonaActual;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\SolicitudDetalle;

final class ResolverSolicitudDetalle
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly RemesaRepository $remesas,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(int $id, array $datos): SolicitudDetalle
    {
        $ctx = $this->ambito->ejecutar();
        $solicitud = $this->remesas->solicitudPorId($id);
        if ($solicitud === null || $solicitud->personaId !== $ctx->personaId) {
            throw new InvalidArgumentException('Solicitud no encontrada');
        }
        if ($solicitud->estado !== 'pendiente' || $solicitud->id === null) {
            throw new InvalidArgumentException('Esa solicitud ya está resuelta');
        }
        $estado = strtolower(trim((string) ($datos['estado'] ?? '')));
        if (!in_array($estado, ['autorizada', 'denegada'], true)) {
            throw new InvalidArgumentException('Indique autorizada o denegada');
        }
        $motivo = trim((string) ($datos['motivo'] ?? ''));

        return $this->remesas->guardarSolicitud(new SolicitudDetalle(
            $solicitud->id,
            $solicitud->remesaLineaId,
            $solicitud->solicitadaPor,
            $solicitud->solicitadaAt,
            $estado,
            null,
            $motivo !== '' ? $motivo : null,
            $solicitud->remesaId,
            $solicitud->personaId,
            $solicitud->codigoMaestro,
            $solicitud->anio,
            $solicitud->mes,
            $solicitud->version,
        ));
    }
}
