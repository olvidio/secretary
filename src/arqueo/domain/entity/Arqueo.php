<?php

declare(strict_types=1);

namespace src\arqueo\domain\entity;

use DateTimeImmutable;
use src\shared\domain\value_objects\Dinero;

final class Arqueo
{
    /**
     * @param array<string, mixed> $desglose
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $cuenta,
        public readonly DateTimeImmutable $fecha,
        public readonly array $desglose,
        public readonly Dinero $totalDinero,
        public readonly Dinero $totalVales,
        public readonly Dinero $total,
        public readonly ?int $cuentaFisicaId = null,
        public readonly ?int $ejercicioId = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'cuenta' => $this->cuenta,
            'fecha' => $this->fecha->format('Y-m-d'),
            'desglose' => $this->desglose,
            'total_dinero' => $this->totalDinero->toString(),
            'total_vales' => $this->totalVales->toString(),
            'total' => $this->total->toString(),
            'total_es' => $this->total->formatEs(),
            'cuenta_fisica_id' => $this->cuentaFisicaId,
            'ejercicio_id' => $this->ejercicioId,
            'total_cents' => $this->total->toCents(),
        ];
    }
}
