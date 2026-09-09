<?php

declare(strict_types=1);

namespace src\asientos\domain\entity;

use InvalidArgumentException;

final class Movimiento
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $orden,
        public readonly int $cuentaId,
        public readonly ?int $personaId,
        public readonly int $debeCents,
        public readonly int $haberCents,
    ) {
        if ($debeCents < 0 || $haberCents < 0) {
            throw new InvalidArgumentException('Debe y haber no pueden ser negativos');
        }
        $tieneDebe = $debeCents > 0;
        $tieneHaber = $haberCents > 0;
        if ($tieneDebe === $tieneHaber) {
            throw new InvalidArgumentException('Exactamente uno de debe o haber debe ser mayor que cero');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'orden' => $this->orden,
            'cuenta_id' => $this->cuentaId,
            'persona_id' => $this->personaId,
            'debe_cents' => $this->debeCents,
            'haber_cents' => $this->haberCents,
        ];
    }
}
