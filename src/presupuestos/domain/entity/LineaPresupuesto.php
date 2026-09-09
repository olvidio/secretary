<?php

declare(strict_types=1);

namespace src\presupuestos\domain\entity;

use src\shared\domain\value_objects\Dinero;

final class LineaPresupuesto
{
    public function __construct(
        public readonly string $cuenta,
        public readonly string $conceptoCodigo,
        public readonly Dinero $previsto,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'cuenta' => $this->cuenta,
            'concepto_codigo' => $this->conceptoCodigo,
            'previsto' => $this->previsto->toString(),
            'previsto_es' => $this->previsto->formatEs(),
        ];
    }
}
