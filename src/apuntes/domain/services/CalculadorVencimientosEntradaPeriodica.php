<?php

declare(strict_types=1);

namespace src\apuntes\domain\services;

use DateTimeImmutable;
use src\apuntes\domain\entity\EntradaPeriodica;
use src\apuntes\domain\value_objects\PeriodicidadEntrada;

/** Ocurrencias de una definición hasta una fecha tope, excluyendo las ya ejecutadas. */
final class CalculadorVencimientosEntradaPeriodica
{
    private const MAX_OCURRENCIAS = 240;

    /**
     * @param list<string> $fechasEjecutadasYmd
     * @return list<DateTimeImmutable>
     */
    public function pendientes(
        EntradaPeriodica $def,
        DateTimeImmutable $hasta,
        array $fechasEjecutadasYmd,
    ): array {
        if (!$def->activa || $def->fechaAncla > $hasta) {
            return [];
        }
        $hechas = array_fill_keys($fechasEjecutadasYmd, true);
        $cursor = $def->fechaAncla;
        $out = [];
        $guard = 0;
        while ($cursor <= $hasta && $guard < self::MAX_OCURRENCIAS) {
            ++$guard;
            $ymd = $cursor->format('Y-m-d');
            if (!isset($hechas[$ymd])) {
                $out[] = $cursor;
            }
            $cursor = $this->siguiente($def->periodicidad, $cursor, $def->fechaAncla);
        }

        return $out;
    }

    private function siguiente(
        PeriodicidadEntrada $periodicidad,
        DateTimeImmutable $actual,
        DateTimeImmutable $ancla,
    ): DateTimeImmutable {
        $meses = match ($periodicidad->valor) {
            PeriodicidadEntrada::MENSUAL => 1,
            PeriodicidadEntrada::TRIMESTRAL => 3,
            PeriodicidadEntrada::ANUAL => 12,
            default => 1,
        };

        return $this->sumarMesesManteniendoDia($actual, $meses, (int) $ancla->format('d'));
    }

    private function sumarMesesManteniendoDia(
        DateTimeImmutable $desde,
        int $meses,
        int $diaPreferido,
    ): DateTimeImmutable {
        $y = (int) $desde->format('Y');
        $m = (int) $desde->format('n') + $meses;
        while ($m > 12) {
            $m -= 12;
            ++$y;
        }
        $ultimo = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');
        $dia = min(max(1, $diaPreferido), $ultimo);

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $dia));
    }
}
