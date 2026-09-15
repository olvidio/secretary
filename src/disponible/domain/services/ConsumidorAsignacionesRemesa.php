<?php

declare(strict_types=1);

namespace src\disponible\domain\services;

/**
 * Resta de las líneas de remesa las 7x que ya se apuntaron al confirmar.
 *
 * @phpstan-type Pendiente array{id:int, codigo_maestro:string, pendiente_cents:int}
 * @phpstan-type Linea array{codigo_maestro:string, importe_cents:int, cuenta_id?:int, tipo?:string}
 * @phpstan-type Consumo array{linea_id:int, importe_cents:int}
 */
final class ConsumidorAsignacionesRemesa
{
    /**
     * @param list<Linea> $lineas
     * @param list<Pendiente> $pendientes
     * @return array{lineas: list<Linea>, consumos: list<Consumo>}
     */
    public static function aplicar(array $lineas, array $pendientes): array
    {
        $porCodigo = [];
        foreach ($pendientes as $p) {
            $cod = $p['codigo_maestro'];
            if (!isset($porCodigo[$cod])) {
                $porCodigo[$cod] = [];
            }
            $porCodigo[$cod][] = $p;
        }
        $consumos = [];
        $out = [];
        foreach ($lineas as $linea) {
            $codigo = $linea['codigo_maestro'];
            $cents = $linea['importe_cents'];
            if ($cents === 0 || !self::esLabores($codigo) || !isset($porCodigo[$codigo])) {
                if ($cents !== 0) {
                    $out[] = $linea;
                }
                continue;
            }
            $restante = $cents;
            foreach ($porCodigo[$codigo] as $i => $pend) {
                if ($restante <= 0) {
                    break;
                }
                $take = min($restante, $pend['pendiente_cents']);
                if ($take <= 0) {
                    continue;
                }
                $consumos[] = ['linea_id' => $pend['id'], 'importe_cents' => $take];
                $porCodigo[$codigo][$i]['pendiente_cents'] -= $take;
                $restante -= $take;
            }
            if ($restante !== 0) {
                $copia = $linea;
                $copia['importe_cents'] = $restante;
                $out[] = $copia;
            }
        }

        return ['lineas' => $out, 'consumos' => $consumos];
    }

    public static function esLabores(string $codigo): bool
    {
        return preg_match('/^7\d{1,2}$/', $codigo) === 1;
    }
}
