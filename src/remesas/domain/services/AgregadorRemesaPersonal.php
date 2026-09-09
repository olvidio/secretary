<?php

declare(strict_types=1);

namespace src\remesas\domain\services;

use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\entity\Asiento;
use src\conceptos\domain\services\CatalogoConceptos;
use src\remesas\domain\entity\RemesaLinea;

/**
 * Agrega el libro X de un mes por codigo_maestro. Ignora tesorería, traspasos
 * y periodificación (D6): eso no viaja al centro.
 */
final class AgregadorRemesaPersonal
{
    /**
     * @param list<Asiento> $asientos
     * @param array<int, Cuenta> $cuentasPorId
     * @return list<RemesaLinea>
     */
    public static function agregar(array $asientos, array $cuentasPorId): array
    {
        /** @var array<string, array{importe:int, detalle: array<string, array{codigo:string,nombre:string,cents:int}>}> $porMaestro */
        $porMaestro = [];
        foreach ($asientos as $asiento) {
            if (in_array($asiento->tipo, ['traspaso', 'periodificacion'], true)) {
                continue;
            }
            foreach ($asiento->movimientos as $mov) {
                $cuenta = $cuentasPorId[$mov->cuentaId] ?? null;
                if ($cuenta === null || !in_array($cuenta->tipo, ['ingreso', 'gasto'], true)) {
                    continue;
                }
                $cents = $cuenta->tipo === 'gasto'
                    ? $mov->debeCents - $mov->haberCents
                    : $mov->haberCents - $mov->debeCents;
                $maestro = (string) $cuenta->codigoMaestro;
                if (!isset($porMaestro[$maestro])) {
                    $porMaestro[$maestro] = ['importe' => 0, 'detalle' => []];
                }
                $porMaestro[$maestro]['importe'] += $cents;
                $clave = $cuenta->codigo;
                if (!isset($porMaestro[$maestro]['detalle'][$clave])) {
                    $porMaestro[$maestro]['detalle'][$clave] = [
                        'codigo' => $cuenta->codigo,
                        'nombre' => $cuenta->nombre,
                        'cents' => 0,
                    ];
                }
                $porMaestro[$maestro]['detalle'][$clave]['cents'] += $cents;
            }
        }

        ksort($porMaestro, SORT_STRING);
        $lineas = [];
        foreach ($porMaestro as $maestro => $datos) {
            if ($datos['importe'] === 0) {
                continue;
            }
            $detalle = array_values($datos['detalle']);
            usort($detalle, static fn (array $a, array $b): int => $a['codigo'] <=> $b['codigo']);
            $lineas[] = new RemesaLinea(null, null, (string) $maestro, $datos['importe'], $detalle);
        }

        return $lineas;
    }

    public static function nombreMaestro(string $codigo): string
    {
        foreach (CatalogoConceptos::todos() as $c) {
            if ($c['cuenta'] === 'P' && $c['codigo'] === $codigo) {
                return $c['nombre'] !== '' ? $c['nombre'] : $codigo;
            }
        }

        return $codigo;
    }
}
