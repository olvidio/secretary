<?php

declare(strict_types=1);

namespace src\asientos\domain\entity;

use DateTimeImmutable;
use InvalidArgumentException;
use src\asientos\domain\exceptions\AsientoDescuadrado;

final class Asiento
{
    /**
     * @param list<Movimiento> $movimientos
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $ejercicioId,
        public readonly string $libro,
        public readonly ?int $numero,
        public readonly DateTimeImmutable $fecha,
        public readonly ?string $glosa,
        public readonly string $tipo,
        public readonly string $origen,
        public readonly ?int $personaId,
        public readonly array $movimientos,
        public readonly ?string $conceptoCodigo = null,
        public readonly ?int $asientoParId = null,
        public readonly ?DateTimeImmutable $fechaOperacion = null,
        public readonly ?int $remesaId = null,
    ) {
        if (!in_array($libro, ['P', 'G', 'X'], true)) {
            throw new InvalidArgumentException('Libro no válido: ' . $libro);
        }
        if (count($movimientos) < 2) {
            throw new InvalidArgumentException('Un asiento requiere al menos dos movimientos');
        }
        $this->assertCuadre();
    }

    /** Fecha en que se ejecutó la operación; si no hay dato, coincide con la de imputación. */
    public function fechaOperacion(): DateTimeImmutable
    {
        return $this->fechaOperacion ?? $this->fecha;
    }

    public function assertCuadre(): void
    {
        $debe = 0;
        $haber = 0;
        foreach ($this->movimientos as $mov) {
            $debe += $mov->debeCents;
            $haber += $mov->haberCents;
        }
        if ($debe !== $haber) {
            throw new AsientoDescuadrado($debe, $haber);
        }
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->ejercicioId,
            $this->libro,
            $this->numero,
            $this->fecha,
            $this->glosa,
            $this->tipo,
            $this->origen,
            $this->personaId,
            $this->movimientos,
            $this->conceptoCodigo,
            $this->asientoParId,
            $this->fechaOperacion,
            $this->remesaId,
        );
    }

    public function withAsientoParId(?int $asientoParId): self
    {
        return new self(
            $this->id,
            $this->ejercicioId,
            $this->libro,
            $this->numero,
            $this->fecha,
            $this->glosa,
            $this->tipo,
            $this->origen,
            $this->personaId,
            $this->movimientos,
            $this->conceptoCodigo,
            $asientoParId,
            $this->fechaOperacion,
            $this->remesaId,
        );
    }

    public function withNumero(int $numero): self
    {
        return new self(
            $this->id,
            $this->ejercicioId,
            $this->libro,
            $numero,
            $this->fecha,
            $this->glosa,
            $this->tipo,
            $this->origen,
            $this->personaId,
            $this->movimientos,
            $this->conceptoCodigo,
            $this->asientoParId,
            $this->fechaOperacion,
            $this->remesaId,
        );
    }

    public function withFechaOperacion(DateTimeImmutable $fechaOperacion): self
    {
        return new self(
            $this->id,
            $this->ejercicioId,
            $this->libro,
            $this->numero,
            $this->fecha,
            $this->glosa,
            $this->tipo,
            $this->origen,
            $this->personaId,
            $this->movimientos,
            $this->conceptoCodigo,
            $this->asientoParId,
            $fechaOperacion,
            $this->remesaId,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ejercicio_id' => $this->ejercicioId,
            'libro' => $this->libro,
            'numero' => $this->numero,
            'fecha' => $this->fecha->format('Y-m-d'),
            'fecha_operacion' => $this->fechaOperacion()->format('Y-m-d'),
            'glosa' => $this->glosa,
            'tipo' => $this->tipo,
            'origen' => $this->origen,
            'persona_id' => $this->personaId,
            'concepto_codigo' => $this->conceptoCodigo,
            'asiento_par_id' => $this->asientoParId,
            'remesa_id' => $this->remesaId,
            'movimientos' => array_map(static fn (Movimiento $m) => $m->toArray(), $this->movimientos),
        ];
    }
}
