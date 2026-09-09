<?php

declare(strict_types=1);

namespace src\apuntes\domain\value_objects;

final class LineaPlantillaApunte
{
    public function __construct(
        public readonly string $cuenta,
        public readonly string $origen,
        public readonly string $conceptoCodigo,
        public readonly ?string $observaciones,
        public readonly int $orden = 0,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'orden' => $this->orden,
            'cuenta' => $this->cuenta,
            'origen' => $this->origen,
            'concepto_codigo' => $this->conceptoCodigo,
            'observaciones' => $this->observaciones,
        ];
    }
}
