<?php

declare(strict_types=1);

namespace src\importacion\domain\services;

use src\importacion\domain\value_objects\LineaExtractoBanco;

/**
 * Dos apuntes del mismo día, concepto e importe son movimientos distintos
 * (el extracto a veces solo los separa por el saldo). La primera aparición
 * conserva la huella, para que un extracto ya importado no se duplique;
 * las siguientes llevan un sufijo estable según el orden del fichero.
 */
final class HuellasExtracto
{
    /**
     * @param list<LineaExtractoBanco> $lineas
     * @return list<LineaExtractoBanco>
     */
    public static function distinguirIguales(array $lineas): array
    {
        $vistos = [];
        $out = [];
        foreach ($lineas as $linea) {
            $n = $vistos[$linea->huella] ?? 0;
            $vistos[$linea->huella] = $n + 1;
            if ($n === 0) {
                $out[] = $linea;
                continue;
            }
            $out[] = new LineaExtractoBanco(
                $linea->fecha,
                $linea->cents,
                $linea->concepto,
                $linea->huella . '#' . ($n + 1),
                $linea->categoriaOrigen,
            );
        }

        return $out;
    }
}
