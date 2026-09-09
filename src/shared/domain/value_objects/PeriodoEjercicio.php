<?php

declare(strict_types=1);

namespace src\shared\domain\value_objects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Ejercicio de período libre (decisión D11, Fase 2 de docs/dev/plan_ampliaciones.md).
 *
 * Ya no hay un enum Año/Curso como fuente de verdad: el período lo definen dos
 * fechas reales, `fechaInicio` y `fechaFin` (el fin real del ejercicio, sea cual
 * sea su duración). `fechaCorte` es un dato distinto y no debe confundirse con
 * `fechaFin`: es "hasta dónde se ha contabilizado y hasta dónde calculan los
 * informes hoy", no el final del ejercicio. Un ejercicio Enero-Diciembre con
 * corte en junio tiene fechaFin = 31 de diciembre y fechaCorte = 30 de junio;
 * `contiene()` valida contra fechaInicio..fechaFin (el ejercicio completo), no
 * contra la fecha de corte: un apunte de diciembre es válido aunque el corte
 * esté en junio.
 */
final class PeriodoEjercicio
{
    public function __construct(
        public readonly DateTimeImmutable $fechaInicio,
        public readonly DateTimeImmutable $fechaFin,
        public readonly DateTimeImmutable $fechaCorte,
    ) {
        if ($this->fechaFin < $this->fechaInicio) {
            throw new InvalidArgumentException('fecha_fin no puede ser anterior a fecha_inicio');
        }
    }

    /**
     * Meses del ejercicio completo (de fechaInicio a fechaFin), ambos inclusive.
     * Para un ejercicio Enero-Diciembre da 12, igual que antes con el enum 'Año'.
     */
    public function mesesTotales(): int
    {
        return self::mesesEntre($this->fechaInicio, $this->fechaFin);
    }

    /**
     * Meses transcurridos de fechaInicio a la fecha de corte, ambos inclusive.
     * Equivalente a lo que antes se llamaba `mesesHasta()` (que en realidad ya
     * calculaba contra la fecha de corte, aunque se llamara "fechaCierre").
     */
    public function mesesTranscurridos(): int
    {
        return self::mesesEntre($this->fechaInicio, $this->fechaCorte);
    }

    private static function mesesEntre(DateTimeImmutable $desde, DateTimeImmutable $hasta): int
    {
        return 1
            + (((int) $hasta->format('Y')) - ((int) $desde->format('Y'))) * 12
            + (((int) $hasta->format('n')) - ((int) $desde->format('n')));
    }

    /**
     * Valida contra el ejercicio completo (fechaInicio..fechaFin), nunca contra
     * la fecha de corte: ver la nota de clase.
     */
    public function contiene(DateTimeImmutable $fecha): bool
    {
        $d = $fecha->format('Y-m-d');

        return $d >= $this->fechaInicio->format('Y-m-d')
            && $d <= $this->fechaFin->format('Y-m-d');
    }
}
