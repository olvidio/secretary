<?php

declare(strict_types=1);

namespace src\apuntes\domain\entity;

use src\apuntes\domain\value_objects\LineaPlantillaApunte;

final class PlantillaApunte
{
    /**
     * @param list<LineaPlantillaApunte> $lineas
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $centroId,
        public readonly string $cuenta,
        public readonly string $nombre,
        public readonly bool $activa,
        public readonly int $orden,
        public readonly array $lineas,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $lineas = [];
        foreach ($this->lineas as $linea) {
            $lineas[] = $linea->toArray();
        }

        return [
            'id' => $this->id,
            'cuenta' => $this->cuenta,
            'nombre' => $this->nombre,
            'activa' => $this->activa,
            'orden' => $this->orden,
            'lineas' => $lineas,
            'etiqueta' => $this->etiqueta(),
        ];
    }

    public function etiqueta(): string
    {
        return '↳ ' . $this->nombre;
    }
}
