<?php

declare(strict_types=1);

namespace src\personal\domain\services;

use src\conceptos\domain\services\CatalogoConceptos;

/** Códigos del plan maestro admitidos en el libro X (Fase 7). Sin el 9 (saldo c/c). */
final class CatalogoMaestroPersonal
{
    /**
     * @return list<array{codigo:string,nombre:string,descripcion:string,tipo:string,naturaleza:string,orden:int}>
     */
    public static function cuentas(): array
    {
        $out = [];
        foreach (CatalogoConceptos::todos() as $c) {
            if ($c['cuenta'] !== 'P' || $c['naturaleza'] === 'saldo') {
                continue;
            }
            $tipo = $c['naturaleza'] === 'ingreso' ? 'ingreso' : 'gasto';
            $naturaleza = $tipo === 'ingreso' ? 'acreedora' : 'deudora';
            $out[] = [
                'codigo' => $c['codigo'],
                'nombre' => $c['nombre'] !== '' ? $c['nombre'] : $c['codigo'],
                'descripcion' => $c['descripcion'],
                'tipo' => $tipo,
                'naturaleza' => $naturaleza,
                'orden' => $c['orden'],
            ];
        }

        return $out;
    }

    public static function existe(string $codigoMaestro): bool
    {
        foreach (self::cuentas() as $c) {
            if ($c['codigo'] === $codigoMaestro) {
                return true;
            }
        }

        return false;
    }
}
