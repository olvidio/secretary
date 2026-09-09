<?php

declare(strict_types=1);

namespace src\remesas\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\SolicitudDetalle;

final class SolicitarDetalleRemesa
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly RemesaRepository $remesas,
        private readonly ?int $identidadId,
    ) {
    }

    public function ejecutar(int $remesaId, int $lineaId): SolicitudDetalle
    {
        if ($this->identidadId === null) {
            throw new InvalidArgumentException('Sesión incompleta');
        }
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
        $ultima = $this->remesas->ultimaSolicitudDeLinea($lineaId);
        if ($ultima !== null && $ultima->estado === 'pendiente') {
            return $ultima;
        }

        return $this->remesas->guardarSolicitud(new SolicitudDetalle(
            null,
            $lineaId,
            $this->identidadId,
            new DateTimeImmutable(),
            'pendiente',
            null,
            null,
            (int) $remesa->id,
            $remesa->personaId,
            $linea->codigoMaestro,
            $remesa->anio,
            $remesa->mes,
            $remesa->version,
        ));
    }
}
