<?php

declare(strict_types=1);

namespace src\informes\domain\services;

use src\shared\domain\value_objects\Dinero;

/**
 * Aclaraciones del 613 por los movimientos con fecha de imputación distinta
 * que quedan a caballo de la fecha de cierre. No son errores: explican por qué
 * el saldo (VIII, cuentas personales, caja) no es el que se espera.
 */
final class NotasPeriodificacion613
{
    /**
     * @param list<array{libro:string, imputacion:string, operacion:string, iniciales:string, glosa:string, codigo:string, nombre:string, cents:int}> $filas
     * @return array<string, array{notas: list<string>, observaciones: string}> libro (P/G) => notas y texto para Observaciones
     */
    public static function armar(array $filas, string $corte): array
    {
        $out = [];
        $antes = [];
        $despues = [];
        foreach ($filas as $f) {
            $libro = $f['libro'];
            $importe = Dinero::fromCents($f['cents'])->formatEs();
            $quien = trim(implode(' · ', array_filter([
                strtoupper($f['iniciales']),
                trim($f['codigo'] . ' ' . $f['nombre']),
                trim($f['glosa']),
            ], static fn (string $t): bool => $t !== '')));
            $corto = trim(implode(', ', array_filter([$f['codigo'], trim($f['glosa'])], static fn (string $t): bool => $t !== '')));
            $ini = strtoupper($f['iniciales']);
            if ($f['imputacion'] <= $corte) {
                $out[$libro]['notas'][] = sprintf(
                    _('%s: %s € contados al %s; la contrapartida (caja, banco o cuenta personal) se anota el %s, después de la fecha de cierre.'),
                    $quien,
                    $importe,
                    self::es($f['imputacion']),
                    self::es($f['operacion']),
                );
                $antes[$libro][] = sprintf(_('%s %s € (%s; se anota el %s)'), $ini, $importe, $corto, self::es($f['operacion']));
            } else {
                $out[$libro]['notas'][] = sprintf(
                    _('%s: %s € anotados el %s (caja, banco o cuenta personal) que se cuentan el %s, después de la fecha de cierre.'),
                    $quien,
                    $importe,
                    self::es($f['operacion']),
                    self::es($f['imputacion']),
                );
                $despues[$libro][] = sprintf(_('%s %s € (%s; se cuenta el %s)'), $ini, $importe, $corto, self::es($f['imputacion']));
            }
        }

        foreach ($out as $libro => $datos) {
            $frases = [];
            if (!empty($antes[$libro])) {
                $frases[] = _('Imputado a este período con contrapartida posterior:') . ' ' . implode('; ', $antes[$libro]) . '.';
            }
            if (!empty($despues[$libro])) {
                $frases[] = _('Anotado en este período y contado en uno posterior:') . ' ' . implode('; ', $despues[$libro]) . '.';
            }
            $out[$libro] = ['notas' => $datos['notas'], 'observaciones' => implode(' ', $frases)];
        }

        return $out;
    }

    private static function es(string $ymd): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);

        return $d !== false ? $d->format('d/m/Y') : $ymd;
    }
}
