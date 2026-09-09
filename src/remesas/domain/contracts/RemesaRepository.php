<?php

declare(strict_types=1);

namespace src\remesas\domain\contracts;

use src\remesas\domain\entity\Remesa;
use src\remesas\domain\entity\SolicitudDetalle;

interface RemesaRepository
{
    public function guardarConLineas(Remesa $remesa): Remesa;

    public function marcarEstado(int $id, string $estado, bool $resuelta = false): void;

    public function actualizarNota(int $id, ?string $nota): void;

    public function porId(int $id): ?Remesa;

    /** @return list<Remesa> */
    public function listarDePersona(int $personaId, int $ejercicioId, int $anio, int $mes): array;

    /** @return list<Remesa> */
    public function listarDeCentro(int $centroId, ?string $estado = null): array;

    public function enviadaDe(int $personaId, int $ejercicioId, int $anio, int $mes): ?Remesa;

    public function aceptadaDe(int $personaId, int $ejercicioId, int $anio, int $mes): ?Remesa;

    public function maxVersion(int $personaId, int $ejercicioId, int $anio, int $mes): int;

    public function guardarSolicitud(SolicitudDetalle $solicitud): SolicitudDetalle;

    public function solicitudPorId(int $id): ?SolicitudDetalle;

    public function ultimaSolicitudDeLinea(int $lineaId): ?SolicitudDetalle;

    /** @return list<SolicitudDetalle> */
    public function solicitudesPendientesDePersona(int $personaId): array;

    /**
     * @template T
     * @param callable(): T $trabajo
     * @return T
     */
    public function enTransaccion(callable $trabajo): mixed;
}
