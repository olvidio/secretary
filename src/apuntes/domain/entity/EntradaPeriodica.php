<?php

declare(strict_types=1);

namespace src\apuntes\domain\entity;

use DateTimeImmutable;
use src\apuntes\domain\value_objects\PeriodicidadEntrada;
use src\shared\domain\value_objects\Dinero;

final class EntradaPeriodica
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $centroId,
        public readonly string $iniciales,
        public readonly string $conceptoCodigo,
        public readonly ?string $observaciones,
        public readonly Dinero $cantidad,
        public readonly PeriodicidadEntrada $periodicidad,
        public readonly DateTimeImmutable $fechaAncla,
        public readonly bool $activa,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'iniciales' => $this->iniciales,
            'concepto_codigo' => $this->conceptoCodigo,
            'observaciones' => $this->observaciones,
            'cantidad' => $this->cantidad->toString(),
            'periodicidad' => $this->periodicidad->valor,
            'periodicidad_etiqueta' => $this->periodicidad->etiqueta(),
            'fecha_ancla' => $this->fechaAncla->format('Y-m-d'),
            'activa' => $this->activa,
        ];
    }
}
