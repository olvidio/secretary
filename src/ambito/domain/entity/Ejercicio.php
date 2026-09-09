<?php

declare(strict_types=1);

namespace src\ambito\domain\entity;

use DateTimeImmutable;
use InvalidArgumentException;
use src\shared\domain\value_objects\PeriodoEjercicio;

/**
 * Ejercicio de período libre (D11, docs/dev/plan_ampliaciones.md). `fechaFin` es
 * el fin REAL del ejercicio; `fechaCorte` es la fecha de corte de los informes.
 * Nunca son el mismo campo: ver la nota de clase de `PeriodoEjercicio` sobre esta
 * trampa.
 */
final class Ejercicio
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $centroId,
        public readonly string $etiqueta,
        public readonly DateTimeImmutable $fechaInicio,
        public readonly DateTimeImmutable $fechaFin,
        public readonly DateTimeImmutable $fechaCorte,
        public readonly string $estado = 'abierto',
        public readonly ?int $ejercicioAnteriorId = null,
    ) {
        if ($this->fechaFin <= $this->fechaInicio) {
            throw new InvalidArgumentException('fecha_fin debe ser posterior a fecha_inicio');
        }
        if ($this->fechaCorte < $this->fechaInicio || $this->fechaCorte > $this->fechaFin) {
            throw new InvalidArgumentException('fecha_corte debe estar dentro del ejercicio');
        }
    }

    public function periodo(): PeriodoEjercicio
    {
        return new PeriodoEjercicio($this->fechaInicio, $this->fechaFin, $this->fechaCorte);
    }

    /**
     * Regla de dominio D11: los ejercicios de un mismo centro no se solapan.
     * Dos intervalos [inicio, fin] se solapan si cada uno empieza antes de que
     * termine el otro.
     */
    public function solapaCon(self $otro): bool
    {
        if ($otro->centroId !== $this->centroId) {
            return false;
        }

        return $this->fechaInicio <= $otro->fechaFin && $otro->fechaInicio <= $this->fechaFin;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'centro_id' => $this->centroId,
            'etiqueta' => $this->etiqueta,
            'fecha_inicio' => $this->fechaInicio->format('Y-m-d'),
            'fecha_fin' => $this->fechaFin->format('Y-m-d'),
            'fecha_corte' => $this->fechaCorte->format('Y-m-d'),
            'estado' => $this->estado,
            'ejercicio_anterior_id' => $this->ejercicioAnteriorId,
            'meses_totales' => $this->periodo()->mesesTotales(),
            'meses_transcurridos' => $this->periodo()->mesesTranscurridos(),
        ];
    }
}
