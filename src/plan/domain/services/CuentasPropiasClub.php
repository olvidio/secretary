<?php

declare(strict_types=1);

namespace src\plan\domain\services;

use src\ambito\domain\entity\Cuenta;
use src\conceptos\domain\entity\Concepto;

/**
 * En una associació las cuentas son suyas: las crea, las renombra y les cambia el
 * código (a mano o al importar Grisbi), y viven en `cuentas`. El modelo de plan solo
 * sirve para crearlas; el catálogo de conceptos del centro son sus cuentas y nada más.
 */
final class CuentasPropiasClub
{
    /** Cuentas de servicio del extracto bancario: no se eligen al dar una entrada. */
    private const PREFIJOS_DE_SERVICIO = ['BANCO.PEND.', 'OTRA.'];

    /**
     * Sustituye la parte G del catálogo del plan por las cuentas del centro: el modelo
     * del plan no pinta nada una vez creado el centro (se puede vaciar o borrar).
     *
     * @param list<array<string, mixed>> $conceptos catálogo del plan (ya filtrado por libro)
     * @param list<Cuenta> $cuentas cuentas del centro
     * @return list<array<string, mixed>>
     */
    public static function fusionar(array $conceptos, array $cuentas): array
    {
        $vistos = [];
        $conceptos = array_values(array_filter($conceptos, static fn (array $c): bool => $c['cuenta'] !== 'G'));
        foreach ($cuentas as $cuenta) {
            if (
                $cuenta->libro !== 'G' || $cuenta->personaId !== null || !$cuenta->activo
                || !in_array($cuenta->tipo, ['ingreso', 'gasto', 'patrimonio'], true)
                || isset($vistos[$cuenta->codigo]) || self::esDeServicio($cuenta->codigo)
            ) {
                continue;
            }
            $vistos[$cuenta->codigo] = true;
            $conceptos[] = (new Concepto(
                $cuenta->codigo,
                'G',
                $cuenta->nombre,
                $cuenta->descripcion !== '' ? $cuenta->descripcion : $cuenta->nombre,
                self::naturaleza($cuenta),
                $cuenta->orden,
            ))->toArray();
        }
        usort($conceptos, static fn (array $a, array $b): int => [$a['cuenta']] <=> [$b['cuenta']]
            ?: self::cmpCodigo((string) $a['codigo'], (string) $b['codigo']));

        return $conceptos;
    }

    /** 61.6, 61.7, 61.10: cada tramo numérico, y el grupo antes que sus hijas. */
    public static function cmpCodigo(string $a, string $b): int
    {
        $pa = explode('.', $a);
        $pb = explode('.', $b);
        $n = max(count($pa), count($pb));
        for ($i = 0; $i < $n; $i++) {
            $xa = $pa[$i] ?? '';
            $xb = $pb[$i] ?? '';
            if ($xa === $xb) {
                continue;
            }
            if (ctype_digit($xa) && ctype_digit($xb)) {
                return (int) $xa <=> (int) $xb;
            }

            return strnatcasecmp($xa, $xb);
        }

        return 0;
    }

    private static function esDeServicio(string $codigo): bool
    {
        foreach (self::PREFIJOS_DE_SERVICIO as $prefijo) {
            if (str_starts_with($codigo, $prefijo)) {
                return true;
            }
        }

        return false;
    }

    private static function naturaleza(Cuenta $cuenta): string
    {
        if ($cuenta->tipo === 'patrimonio') {
            return 'disponible';
        }

        return $cuenta->imputable ? $cuenta->tipo : 'grupo-' . $cuenta->tipo;
    }
}
