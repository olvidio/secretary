<?php

declare(strict_types=1);

namespace src\personas\domain\entity;

use DateTimeImmutable;

final class SolicitudVinculoCentro
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $identidadId,
        public readonly int $centroId,
        public readonly int $anio,
        public readonly string $estado,
        public readonly ?int $personaId,
        public readonly ?string $mensaje,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $resolvedAt = null,
        public readonly ?int $resolvedBy = null,
    ) {
    }

    public function esPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'identidad_id' => $this->identidadId,
            'centro_id' => $this->centroId,
            'anio' => $this->anio,
            'estado' => $this->estado,
            'persona_id' => $this->personaId,
            'mensaje' => $this->mensaje,
            'created_at' => $this->createdAt?->format('c'),
            'resolved_at' => $this->resolvedAt?->format('c'),
            'resolved_by' => $this->resolvedBy,
        ];
    }
}
