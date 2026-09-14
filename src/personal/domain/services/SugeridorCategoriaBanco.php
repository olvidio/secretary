<?php

declare(strict_types=1);

namespace src\personal\domain\services;

/**
 * Sugiere la categoría de un movimiento de banco a partir de asignaciones previas.
 * Gana la coincidencia más reciente del mismo sentido (ingreso/gasto).
 */
final class SugeridorCategoriaBanco
{
    /**
     * @param list<array{concepto:string, cuenta_id:int, tipo:string}> $historial más reciente primero
     */
    public static function sugerir(string $concepto, string $sentido, array $historial): ?int
    {
        $clave = ClaveAprendizajeBanco::de($concepto);
        $nucleo = ClaveAprendizajeBanco::nucleo($concepto);
        $porClave = self::buscar($sentido, $historial, static fn (string $c): bool => $clave !== '' && ClaveAprendizajeBanco::de($c) === $clave);
        if ($porClave !== null) {
            return $porClave;
        }
        if ($nucleo === '' || strlen($nucleo) < 4) {
            return null;
        }

        return self::buscar($sentido, $historial, static fn (string $c): bool => ClaveAprendizajeBanco::nucleo($c) === $nucleo);
    }

    /**
     * @param list<array{concepto:string, cuenta_id:int, tipo:string}> $historial
     * @param callable(string):bool $coincide
     */
    private static function buscar(string $sentido, array $historial, callable $coincide): ?int
    {
        foreach ($historial as $fila) {
            $tipo = (string) ($fila['tipo'] ?? '');
            if ($tipo !== $sentido) {
                continue;
            }
            $cuentaId = (int) ($fila['cuenta_id'] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            if ($coincide((string) ($fila['concepto'] ?? ''))) {
                return $cuentaId;
            }
        }

        return null;
    }
}
