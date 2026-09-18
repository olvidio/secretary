<?php

declare(strict_types=1);

namespace src\personas\domain\contracts;

use src\personas\domain\entity\SolicitudVinculoCentro;

interface SolicitudVinculoCentroRepository
{
    public function porId(int $id): ?SolicitudVinculoCentro;

    /** @return list<SolicitudVinculoCentro> */
    public function pendientesDeCentro(int $centroId): array;

    /** @return list<SolicitudVinculoCentro> */
    public function deIdentidad(int $identidadId): array;

    public function pendiente(int $identidadId, int $centroId, int $anio): ?SolicitudVinculoCentro;

    public function pendienteDeIdentidad(int $identidadId): ?SolicitudVinculoCentro;

    public function guardar(SolicitudVinculoCentro $solicitud): SolicitudVinculoCentro;

    public function marcarResuelta(int $id, string $estado, ?int $personaId, int $resolvedBy): void;
}
