<?php

declare(strict_types=1);

namespace src\ambito\domain\entity;

final class CuentaFisica
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $centroId,
        public readonly string $tipo,
        public readonly string $nombre,
        public readonly ?string $iban = null,
        public readonly int $orden = 0,
        public readonly bool $activo = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'centro_id' => $this->centroId,
            'tipo' => $this->tipo,
            'nombre' => $this->nombre,
            'iban' => $this->iban,
            'orden' => $this->orden,
            'activo' => $this->activo,
        ];
    }
}
