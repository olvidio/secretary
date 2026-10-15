<?php

declare(strict_types=1);

namespace src\presupuestos\domain\services;

use src\informes\domain\services\CalculadoraE37;

/** Totales de hoja E37 a partir de importes previstos por código de concepto. */
final class PrevisionResumenE37
{
    /**
     * @param array<string, int> $previstoPorCodigo concepto_codigo => cents
     * @return array<string, string>
     */
    public static function filaPersona(string $nombre, string $iniciales, array $previstoPorCodigo): array
    {
        $porCodigo = [];
        $saldoCc = 0;
        foreach ($previstoPorCodigo as $codigo => $cents) {
            if ($codigo === '9') {
                $saldoCc = $cents;
            } else {
                $porCodigo[$codigo] = $cents;
            }
        }
        $tot = CalculadoraE37::totalesDesdeMovimientos($porCodigo, $saldoCc);
        $fila = [
            'iniciales' => $iniciales,
            'nombre' => $nombre,
        ];
        foreach ($tot as $k => $d) {
            $fila[$k] = $d->toString();
            $fila[$k . '_es'] = $d->formatEs();
        }

        return $fila;
    }
}
