<?php

declare(strict_types=1);

namespace src\disponible\domain\services;

use InvalidArgumentException;

/**
 * Tramos IRPF de donativos (Ley 49/2002 art. 19) y tope de la base
 * (art. 69.1 LIRPF: 10 % de la base liquidable con carácter general).
 * Más vale llenar el primer tramo de varias personas que concentrar
 * el mismo importe en una.
 *
 * @phpstan-type Tramo array{hasta_cents:?int, porcentaje:int}
 * @phpstan-type Config array{tramos: list<Tramo>, maximo_pct: int}
 */
final class TramosDesgravacion
{
    /** Art. 69.1 Ley IRPF: 10 % de la base liquidable. */
    public const MAXIMO_PCT_POR_DEFECTO = 10;

    /**
     * @return list<Tramo>
     */
    public static function porDefecto(): array
    {
        return [
            ['hasta_cents' => 25000, 'porcentaje' => 80],
            ['hasta_cents' => null, 'porcentaje' => 40],
        ];
    }

    public static function normalizarMaximoPct(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return self::MAXIMO_PCT_POR_DEFECTO;
        }
        $n = (int) $raw;
        if ($n < 1 || $n > 100) {
            throw new InvalidArgumentException(_("El máximo debe estar entre 1 y 100 % de la base liquidable"));
        }

        return $n;
    }

    /**
     * Tope de la base de deducción: maximo_pct % de la base liquidable.
     * null si no hay base (no se aplica ese tope).
     */
    public static function topeBaseCents(?int $baseLiquidableCents, int $maximoPct): ?int
    {
        if ($baseLiquidableCents === null || $baseLiquidableCents <= 0) {
            return null;
        }

        return intdiv($baseLiquidableCents * self::normalizarMaximoPct($maximoPct), 100);
    }

    /**
     * @param mixed $raw JSON del centro: lista de tramos o {tramos, maximo_pct}
     * @return Config
     */
    public static function desempaquetar(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return [
                'tramos' => self::porDefecto(),
                'maximo_pct' => self::MAXIMO_PCT_POR_DEFECTO,
            ];
        }
        if (array_is_list($raw)) {
            return [
                'tramos' => self::normalizar($raw),
                'maximo_pct' => self::MAXIMO_PCT_POR_DEFECTO,
            ];
        }
        $tramosRaw = $raw['tramos'] ?? [];

        return [
            'tramos' => self::normalizar(is_array($tramosRaw) ? $tramosRaw : []),
            'maximo_pct' => self::normalizarMaximoPct($raw['maximo_pct'] ?? self::MAXIMO_PCT_POR_DEFECTO),
        ];
    }

    /**
     * @param list<mixed>|array<string, mixed> $tramos
     * @return Config
     */
    public static function empaquetar(array $tramos, mixed $maximoPct): array
    {
        return [
            'tramos' => self::normalizar($tramos),
            'maximo_pct' => self::normalizarMaximoPct($maximoPct),
        ];
    }

    /**
     * @param mixed $raw
     * @return list<Tramo>
     */
    public static function normalizar(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return self::porDefecto();
        }
        $out = [];
        $prevHasta = 0;
        $n = 0;
        foreach ($raw as $fila) {
            if (!is_array($fila)) {
                throw new InvalidArgumentException(_("Cada tramo debe ser un objeto"));
            }
            $pct = (int) ($fila['porcentaje'] ?? 0);
            if ($pct < 0 || $pct > 100) {
                throw new InvalidArgumentException(_("El porcentaje del tramo debe estar entre 0 y 100"));
            }
            $hastaRaw = $fila['hasta_cents'] ?? null;
            $hasta = $hastaRaw === null || $hastaRaw === '' ? null : (int) $hastaRaw;
            if ($hasta !== null && $hasta <= $prevHasta) {
                throw new InvalidArgumentException(_("Los tramos deben ir de menor a mayor importe"));
            }
            $esUltimo = $hasta === null;
            $out[] = ['hasta_cents' => $hasta, 'porcentaje' => $pct];
            if ($hasta !== null) {
                $prevHasta = $hasta;
            }
            $n++;
            if ($esUltimo && $n < count($raw)) {
                throw new InvalidArgumentException(_("Solo el último tramo puede no tener tope"));
            }
        }

        return $out;
    }

    /**
     * Capacidad (céntimos) que aún cabe en este tramo para una persona.
     *
     * @param Tramo $tramo
     */
    public static function capacidadTramo(array $tramo, int $yaDesgravadoCents, ?int $hastaAnteriorCents): int
    {
        $desde = $hastaAnteriorCents ?? 0;
        $hasta = $tramo['hasta_cents'];
        if ($hasta === null) {
            return PHP_INT_MAX;
        }
        $ancho = $hasta - $desde;
        if ($ancho <= 0) {
            return 0;
        }
        $usado = max(0, min($ancho, $yaDesgravadoCents - $desde));

        return $ancho - $usado;
    }
}
