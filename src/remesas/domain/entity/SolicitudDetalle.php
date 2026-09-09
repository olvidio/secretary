<?php

declare(strict_types=1);

namespace src\remesas\domain\entity;

use DateTimeImmutable;
use InvalidArgumentException;

final class SolicitudDetalle
{
    public const ESTADOS = ['pendiente', 'autorizada', 'denegada'];

    public function __construct(
        public readonly ?int $id,
        public readonly int $remesaLineaId,
        public readonly int $solicitadaPor,
        public readonly DateTimeImmutable $solicitadaAt,
        public readonly string $estado,
        public readonly ?DateTimeImmutable $resueltaAt,
        public readonly ?string $motivo,
        public readonly int $remesaId = 0,
        public readonly int $personaId = 0,
        public readonly string $codigoMaestro = '',
        public readonly int $anio = 0,
        public readonly int $mes = 0,
        public readonly int $version = 0,
    ) {
        if (!in_array($this->estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado de solicitud no válido: ' . $this->estado);
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'remesa_linea_id' => $this->remesaLineaId,
            'remesa_id' => $this->remesaId,
            'persona_id' => $this->personaId,
            'codigo_maestro' => $this->codigoMaestro,
            'anio' => $this->anio,
            'mes' => $this->mes,
            'version' => $this->version,
            'solicitada_por' => $this->solicitadaPor,
            'solicitada_at' => $this->solicitadaAt->format('c'),
            'estado' => $this->estado,
            'resuelta_at' => $this->resueltaAt?->format('c'),
            'motivo' => $this->motivo,
        ];
    }
}
