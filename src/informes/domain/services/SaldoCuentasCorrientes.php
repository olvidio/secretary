<?php

declare(strict_types=1);

namespace src\informes\domain\services;

/**
 * Concepto 90: saldo de caja+banco que cada persona comunicó en la remesa.
 * No genera apuntes. En el 613 P es la suma de la última remesa aceptada
 * de cada persona, hasta el mes de la fecha de cierre.
 */
final class SaldoCuentasCorrientes
{
    /**
     * @param list<array{persona_id:int,anio:int,mes:int,version:int,saldo_cents:?int}> $aceptadas
     */
    public static function cents(array $aceptadas, int $anioCorte, int $mesCorte): int
    {
        $mejor = [];
        foreach ($aceptadas as $fila) {
            $anio = (int) $fila['anio'];
            $mes = (int) $fila['mes'];
            if ($anio > $anioCorte || ($anio === $anioCorte && $mes > $mesCorte)) {
                continue;
            }
            $personaId = (int) $fila['persona_id'];
            $orden = ($anio * 100 + $mes) * 1000 + (int) $fila['version'];
            if (!isset($mejor[$personaId]) || $orden > $mejor[$personaId]['orden']) {
                $mejor[$personaId] = [
                    'orden' => $orden,
                    'cents' => (int) ($fila['saldo_cents'] ?? 0),
                ];
            }
        }
        $total = 0;
        foreach ($mejor as $fila) {
            $total += $fila['cents'];
        }

        return $total;
    }
}
