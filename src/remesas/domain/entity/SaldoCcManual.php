<?php

declare(strict_types=1);

namespace src\remesas\domain\entity;

use InvalidArgumentException;
use src\shared\domain\value_objects\Dinero;

/**
 * Concepto 90 anotado a mano: saldo de caja+banco de una persona a fin de mes,
 * cuando no llega por remesa. Informativo; no genera asientos.
 */
final class SaldoCcManual
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $centroId,
        public readonly int $ejercicioId,
        public readonly int $personaId,
        public readonly int $anio,
        public readonly int $mes,
        public readonly int $saldoCents,
        public readonly ?string $nota = null,
    ) {
        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException(_("Mes no válido"));
        }
        if ($anio < 1900 || $anio > 2999) {
            throw new InvalidArgumentException(_("Año no válido"));
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $saldo = Dinero::fromCents($this->saldoCents);

        return [
            'id' => $this->id,
            'persona_id' => $this->personaId,
            'anio' => $this->anio,
            'mes' => $this->mes,
            'saldo_cents' => $this->saldoCents,
            'saldo' => $saldo->toString(),
            'saldo_es' => $saldo->formatEs(),
            'nota' => $this->nota,
        ];
    }
}
