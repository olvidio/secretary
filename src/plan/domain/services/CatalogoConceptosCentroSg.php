<?php

declare(strict_types=1);

namespace src\plan\domain\services;

/**
 * Plan del Secretario de sg (Excel sg-v5). Un solo libro.
 * Los destinos 42–54 se nombran en cada centro (`centro_destinos_sg`); aquí quedan con el código.
 *
 * @phpstan-type Fila array{codigo:string,cuenta:string,nombre:string,descripcion:string,naturaleza:string,orden:int}
 */
final class CatalogoConceptosCentroSg
{
    /** @return list<Fila> */
    public static function filas(): array
    {
        $filas = [
            ['11', 'Aportaciones ordinarias', 'ingreso', 10],
            ['12', 'Aportaciones extraordinarias', 'ingreso', 20],
            ['13', 'Ayudas varias (cp)', 'ingreso', 30],
            ['14', 'Otros donativos', 'ingreso', 40],
            ['21', 'Suministros', 'gasto', 110],
            ['22', 'Alquiler, com. vecinos, seguros e impuestos', 'gasto', 120],
            ['23', 'Instalación y conservación', 'gasto', 130],
            ['24', 'Limpieza, etc.', 'gasto', 140],
            ['25', 'Asociación', 'gasto', 150],
            ['26', 'Suscripciones, papelería, libros, otros', 'gasto', 160],
            ['27', 'Atención cv, crt, etc.', 'gasto', 170],
            ['28', 'Déficit actividades', 'gasto', 180],
            ['32', 'Disponible a 1 de enero', 'disponible', 200],
            ['41', 'Necesidades generales', 'gasto', 410],
        ];
        for ($n = 42; $n <= 54; $n++) {
            $filas[] = [(string) $n, (string) $n, 'gasto', 400 + $n];
        }
        $out = [];
        foreach ($filas as [$codigo, $nombre, $naturaleza, $orden]) {
            $out[] = [
                'codigo' => $codigo,
                'cuenta' => 'G',
                'nombre' => $nombre,
                'descripcion' => $nombre,
                'naturaleza' => $naturaleza,
                'orden' => $orden,
            ];
        }

        return $out;
    }
}
