<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\CentroRepository;
use src\personas\domain\contracts\SolicitudVinculoCentroRepository;
use src\personas\domain\entity\SolicitudVinculoCentro;

final class SolicitarVinculoCentro
{
    public function __construct(
        private readonly SolicitudVinculoCentroRepository $solicitudes,
        private readonly IdentidadRepository $identidades,
        private readonly CentroRepository $centros,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function ejecutar(int $identidadId, array $datos): array
    {
        $centroId = (int) ($datos['centro_id'] ?? 0);
        $anio = (int) ($datos['anio'] ?? 0);
        $mensaje = trim((string) ($datos['mensaje'] ?? ''));
        if ($centroId <= 0) {
            throw new InvalidArgumentException(_("Indique el centro"));
        }
        if ($anio < 2000 || $anio > 2100) {
            throw new InvalidArgumentException(_("Indique el año del ejercicio"));
        }
        if ($this->identidades->centrosDe($identidadId) !== []) {
            throw new InvalidArgumentException(_("Las cuentas de secretario no solicitan acceso como persona"));
        }
        if ($this->identidades->tienePersonaEnAlgunCentro($identidadId)) {
            throw new InvalidArgumentException(_("Ya tiene un centro vinculado. Desvincúlese antes de solicitar otro."));
        }
        if ($this->solicitudes->pendienteDeIdentidad($identidadId) !== null) {
            throw new InvalidArgumentException(_("Ya hay una solicitud pendiente"));
        }
        $centro = $this->centros->porId($centroId);
        if ($centro === null || !$centro->activo) {
            throw new InvalidArgumentException(_("Centro no encontrado"));
        }
        if ($centro->tipo !== 'n') {
            throw new InvalidArgumentException(_("Solo puede solicitarse acceso a centros de tipo n"));
        }

        $solicitud = $this->solicitudes->guardar(new SolicitudVinculoCentro(
            null,
            $identidadId,
            $centroId,
            $anio,
            'pendiente',
            null,
            $mensaje !== '' ? $mensaje : null,
        ));

        return $solicitud->toArray();
    }
}
