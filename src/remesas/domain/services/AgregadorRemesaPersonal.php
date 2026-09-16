<?php

declare(strict_types=1);

namespace src\remesas\domain\services;

use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\entity\Asiento;
use src\conceptos\domain\services\CatalogoConceptos;
use src\personal\domain\services\CatalogoMaestroPersonal;
use src\remesas\domain\entity\RemesaLinea;

/**
 * Agrega el libro X de un mes por codigo_maestro. Ignora tesorería, traspasos,
 * periodificación y cuentas fuera del plan P (D6): eso no viaja al centro.
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
        /** @var array<string, array{importe:int, detalle: array<string, array{codigo:string,nombre:string,cents:int,generales:array<string,array{concepto:string,cents:int}>}>}> $porMaestro */
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
                $maestro = (string) $cuenta->codigoMaestro;
                if (!CatalogoMaestroPersonal::existe($maestro)) {
                    continue;
                }
                $cents = $cuenta->tipo === 'gasto'
                    ? $mov->debeCents - $mov->haberCents
                    : $mov->haberCents - $mov->debeCents;
                if ($cents === 0) {
                    continue;
                }
                if (!isset($porMaestro[$maestro])) {
                    $porMaestro[$maestro] = ['importe' => 0, 'detalle' => []];
                }
                $clave = $cuenta->codigo;
                if (!isset($porMaestro[$maestro]['detalle'][$clave])) {
                    $porMaestro[$maestro]['detalle'][$clave] = [
                        'codigo' => $cuenta->codigo,
                        'nombre' => $cuenta->nombre,
                        'cents' => 0,
                        'generales' => [],
                        'plantillas' => [],
                    ];
                }
                if ($asiento->plantillaApunteId !== null && $cuenta->tipo === 'gasto') {
                    $pid = $asiento->plantillaApunteId;
                    if (!isset($porMaestro[$maestro]['detalle'][$clave]['plantillas'][$pid])) {
                        $porMaestro[$maestro]['detalle'][$clave]['plantillas'][$pid] = [
                            'plantilla_id' => $pid,
                            'cents' => 0,
                        ];
                    }
                    $porMaestro[$maestro]['detalle'][$clave]['plantillas'][$pid]['cents'] += $cents;
                    continue;
                }
                $porMaestro[$maestro]['importe'] += $cents;
                $porMaestro[$maestro]['detalle'][$clave]['cents'] += $cents;
                if ($asiento->gastoGenerales && $asiento->conceptoGenerales !== null
                    && $cuenta->tipo === 'gasto' && $cents !== 0) {
                    $cg = $asiento->conceptoGenerales;
                    if (!isset($porMaestro[$maestro]['detalle'][$clave]['generales'][$cg])) {
                        $porMaestro[$maestro]['detalle'][$clave]['generales'][$cg] = [
                            'concepto' => $cg,
                            'cents' => 0,
                        ];
                    }
                    $porMaestro[$maestro]['detalle'][$clave]['generales'][$cg]['cents'] += $cents;
                }
            }
        }

        ksort($porMaestro, SORT_STRING);
        $lineas = [];
        foreach ($porMaestro as $maestro => $datos) {
            if ($datos['importe'] === 0 && !self::detalleTienePlantillas($datos['detalle'])) {
                continue;
            }
            $detalle = [];
            foreach ($datos['detalle'] as $item) {
                $generales = array_values($item['generales'] ?? []);
                usort($generales, static fn (array $a, array $b): int => $a['concepto'] <=> $b['concepto']);
                $plantillas = array_values($item['plantillas'] ?? []);
                usort($plantillas, static fn (array $a, array $b): int => $a['plantilla_id'] <=> $b['plantilla_id']);
                $fila = [
                    'codigo' => $item['codigo'],
                    'nombre' => $item['nombre'],
                    'cents' => $item['cents'],
                ];
                if ($generales !== []) {
                    $fila['generales'] = $generales;
                }
                if ($plantillas !== []) {
                    $fila['plantillas'] = $plantillas;
                }
                $detalle[] = $fila;
            }
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

    /**
     * @param array<string, array<string, mixed>> $detalle
     */
    private static function detalleTienePlantillas(array $detalle): bool
    {
        foreach ($detalle as $item) {
            if (!empty($item['plantillas'])) {
                return true;
            }
        }

        return false;
    }
}
