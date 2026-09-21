<?php

declare(strict_types=1);

namespace src\apuntes\domain\services;

use src\personal\domain\services\ClaveAprendizajeBanco;

/** Sugiere concepto G a partir de categorizaciones previas del extracto. */
final class SugeridorConceptoBancoCentro
{
    /**
     * @param list<array{concepto:string, concepto_codigo:string, tipo:string}> $historial
     */
    public static function sugerir(string $conceptoExtracto, string $sentido, array $historial): ?string
    {
        $clave = ClaveAprendizajeBanco::de($conceptoExtracto);
        $nucleo = ClaveAprendizajeBanco::nucleo($conceptoExtracto);
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
     * @param list<array{concepto:string, concepto_codigo:string, tipo:string}> $historial
     * @param callable(string):bool $coincide
     */
    private static function buscar(string $sentido, array $historial, callable $coincide): ?string
    {
        foreach ($historial as $fila) {
            $tipo = (string) ($fila['tipo'] ?? '');
            if ($tipo !== $sentido) {
                continue;
            }
            $codigo = trim((string) ($fila['concepto_codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            if ($coincide((string) ($fila['concepto'] ?? ''))) {
                return $codigo;
            }
        }

        return null;
    }
}
