<?php

declare(strict_types=1);

namespace src\apuntes\domain\entity;

use DateTimeImmutable;
use src\shared\domain\value_objects\Dinero;

final class Apunte
{
    public function __construct(
        public readonly ?int $id,
        public readonly DateTimeImmutable $fecha,
        public readonly string $cuenta,
        public readonly string $origen,
        public readonly ?string $iniciales,
        public readonly string $conceptoCodigo,
        public readonly ?string $observaciones,
        public readonly Dinero $cantidad,
        public readonly bool $esCierre = false,
        public readonly ?int $parId = null,
    ) {
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->fecha,
            $this->cuenta,
            $this->origen,
            $this->iniciales,
            $this->conceptoCodigo,
            $this->observaciones,
            $this->cantidad,
            $this->esCierre,
            $this->parId,
        );
    }

    public function withParId(?int $parId): self
    {
        return new self(
            $this->id,
            $this->fecha,
            $this->cuenta,
            $this->origen,
            $this->iniciales,
            $this->conceptoCodigo,
            $this->observaciones,
            $this->cantidad,
            $this->esCierre,
            $parId,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->format('Y-m-d'),
            'fecha_es' => $this->fecha->format('d/m/Y'),
            'cuenta' => $this->cuenta,
            'origen' => $this->origen,
            'iniciales' => $this->iniciales,
            'concepto_codigo' => $this->conceptoCodigo,
            'observaciones' => $this->observaciones,
            'cantidad' => $this->cantidad->toString(),
            'cantidad_es' => $this->cantidad->formatEs(),
            'es_cierre' => $this->esCierre,
            'par_id' => $this->parId,
        ];
    }
}
