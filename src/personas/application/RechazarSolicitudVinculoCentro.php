<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use src\personas\domain\contracts\SolicitudVinculoCentroRepository;

final class RechazarSolicitudVinculoCentro
{
    public function __construct(private readonly SolicitudVinculoCentroRepository $solicitudes)
    {
    }

    public function ejecutar(int $centroId, int $solicitudId, int $resolvedBy): void
    {
        $solicitud = $this->solicitudes->porId($solicitudId);
        if ($solicitud === null || $solicitud->centroId !== $centroId || !$solicitud->esPendiente()) {
            throw new InvalidArgumentException('Solicitud no encontrada o ya resuelta');
        }
        $this->solicitudes->marcarResuelta($solicitudId, 'rechazada', null, $resolvedBy);
    }
}
