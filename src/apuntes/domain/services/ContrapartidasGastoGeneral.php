<?php

declare(strict_types=1);

namespace src\apuntes\domain\services;

/**
 * Gasto de generales atribuido a una persona: P/111, P/21, G/11 y el gasto anotado.
 */
final class ContrapartidasGastoGeneral
{
    /**
     * @return list<array{cuenta:string,origen:string,concepto_codigo:string,observaciones:?string}>|null
     */
    public function lineas(
        string $cuenta,
        string $origen,
        string $conceptoCodigo,
        string $naturaleza,
        string $iniciales,
        bool $aportaVivienda,
        ?string $observaciones,
    ): ?array {
        if (strtoupper($cuenta) !== 'G') {
            return null;
        }
        if (trim($iniciales) === '') {
            return null;
        }
        if ($naturaleza !== 'gasto') {
            return null;
        }
        $conceptoCodigo = trim($conceptoCodigo);
        if ($conceptoCodigo === '') {
            return null;
        }
        if (!$aportaVivienda) {
            return null;
        }
        $origen = strtoupper($origen);
        if (!in_array($origen, ['A', 'B', 'C'], true)) {
            $origen = 'A';
        }
        $obs = $observaciones === null || $observaciones === '' ? null : $observaciones;

        return [
            ['cuenta' => 'P', 'origen' => 'A', 'concepto_codigo' => '111', 'observaciones' => null],
            ['cuenta' => 'P', 'origen' => 'A', 'concepto_codigo' => '21', 'observaciones' => $obs],
            ['cuenta' => 'G', 'origen' => 'A', 'concepto_codigo' => '11', 'observaciones' => $obs],
            ['cuenta' => 'G', 'origen' => $origen, 'concepto_codigo' => $conceptoCodigo, 'observaciones' => $obs],
        ];
    }
}
