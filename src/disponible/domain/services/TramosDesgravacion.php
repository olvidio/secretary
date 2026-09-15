<?php

declare(strict_types=1);

namespace src\disponible\domain\services;

use InvalidArgumentException;

/**
 * Tramos IRPF de donativos: más vale llenar el primer tramo de varias
 * personas que concentrar el mismo importe en una.
 *
 * @phpstan-type Tramo array{hasta_cents:?int, porcentaje:int}
 */
final class TramosDesgravacion
{
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
                throw new InvalidArgumentException('Cada tramo debe ser un objeto');
            }
            $pct = (int) ($fila['porcentaje'] ?? 0);
            if ($pct < 0 || $pct > 100) {
                throw new InvalidArgumentException('El porcentaje del tramo debe estar entre 0 y 100');
            }
            $hastaRaw = $fila['hasta_cents'] ?? null;
            $hasta = $hastaRaw === null || $hastaRaw === '' ? null : (int) $hastaRaw;
            if ($hasta !== null && $hasta <= $prevHasta) {
                throw new InvalidArgumentException('Los tramos deben ir de menor a mayor importe');
            }
            $esUltimo = $hasta === null;
            $out[] = ['hasta_cents' => $hasta, 'porcentaje' => $pct];
            if ($hasta !== null) {
                $prevHasta = $hasta;
            }
            $n++;
            if ($esUltimo && $n < count($raw)) {
                throw new InvalidArgumentException('Solo el último tramo puede no tener tope');
            }
        }
        $ultimo = $out[array_key_last($out)];
        if ($ultimo['hasta_cents'] !== null) {
            $out[] = ['hasta_cents' => null, 'porcentaje' => $ultimo['porcentaje']];
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
