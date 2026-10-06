<?php

declare(strict_types=1);

namespace src\asientos\domain\services;

use src\plan\domain\services\ContabilidadCentroSg;

/**
 * Convierte un asiento legacy tipo traspaso (solo caja/banco) en gasto + caja (H16s).
 */
final class ReparadorTraspasoAFilaGastoCentroSg
{
    /**
     * @param list<array{codigo_maestro:string,tipo:string,debe:int,haber:int,cuenta_id:int,codigo:string}> $legs
     * @return array{concepto_codigo:string,caja_cuenta_id:int,importe:int}|null
     */
    public static function proponer(array $legs): ?array
    {
        if (count($legs) !== 2) {
            return null;
        }
        $caja = null;
        $banco = null;
        foreach ($legs as $leg) {
            if ($leg['tipo'] !== 'tesoreria') {
                return null;
            }
            if ($leg['codigo_maestro'] === 'CAJA') {
                $caja = $leg;
            }
            if ($leg['codigo_maestro'] === 'BANCO') {
                $banco = $leg;
            }
        }
        if ($caja === null || $banco === null) {
            return null;
        }

        $concepto = self::inferirConcepto($caja, $banco);
        if (!ContabilidadCentroSg::esCodigoDestino($concepto)) {
            return null;
        }

        $importe = max($caja['debe'], $caja['haber'], $banco['debe'], $banco['haber']);
        if ($importe <= 0) {
            return null;
        }

        return [
            'concepto_codigo' => $concepto,
            'caja_cuenta_id' => (int) $caja['cuenta_id'],
            'importe' => $importe,
        ];
    }

    /**
     * @param array{codigo_maestro:string,debe:int,haber:int} $caja
     * @param array{codigo_maestro:string,debe:int,haber:int} $banco
     */
    private static function inferirConcepto(array $caja, array $banco): string
    {
        if ($caja['debe'] > $caja['haber']) {
            return '41';
        }
        if ($banco['debe'] > $banco['haber']) {
            return '42';
        }

        return '41';
    }
}
