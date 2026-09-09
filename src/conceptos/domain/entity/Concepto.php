<?php

declare(strict_types=1);

namespace src\conceptos\domain\entity;

final class Concepto
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $cuenta,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $naturaleza,
        public readonly int $orden,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'cuenta' => $this->cuenta,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'naturaleza' => $this->naturaleza,
            'orden' => $this->orden,
            'etiqueta' => $this->codigo . ' ' . $this->nombre,
        ];
    }
}
