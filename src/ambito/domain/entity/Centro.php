<?php

declare(strict_types=1);

namespace src\ambito\domain\entity;

final class Centro
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $tipoCierre,
        public readonly bool $activo = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tipo_cierre' => $this->tipoCierre,
            'activo' => $this->activo,
        ];
    }
}
