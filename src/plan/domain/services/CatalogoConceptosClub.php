<?php

declare(strict_types=1);

namespace src\plan\domain\services;

/**
 * Plantilla inicial del plan Club. Un fichero Grisbi nuevo puede añadir hojas.
 *
 * @phpstan-type Fila array{codigo:string,cuenta:string,nombre:string,descripcion:string,naturaleza:string,orden:int}
 */
final class CatalogoConceptosClub
{
    /** @return list<Fila> */
    public static function filas(): array
    {
        $filas = [
            ['60', 'Despeses d\'activitat', 'grupo-gasto', 10],
            ['60.1', 'Material i serveis externs', 'gasto', 11],
            ['60.2', 'Professors', 'gasto', 12],
            ['61', 'Despeses generals', 'grupo-gasto', 20],
            ['61.1', 'Subministraments', 'gasto', 21],
            ['61.2', 'Manteniment', 'gasto', 22],
            ['61.3', 'Neteja', 'gasto', 23],
            ['61.4', 'Assegurances', 'gasto', 24],
            ['61.5', 'Despeses bancàries', 'gasto', 25],
            ['61.6', 'Taxes', 'gasto', 26],
            ['70', 'Ingressos del club', 'grupo-ingreso', 30],
            ['70.1', 'Socis', 'ingreso', 31],
            ['70.2', 'Donatius', 'ingreso', 32],
            ['80', 'Altres activitats', 'grupo-gasto', 40],
            ['80.1', 'Campament', 'gasto', 41],
            ['80.2', 'Torreciutat', 'gasto', 42],
            ['80.3', 'Altres cv extraordinàries', 'gasto', 43],
            ['80.4', 'Crt', 'gasto', 44],
            ['80.5', 'Calçotada', 'gasto', 45],
            ['80.6', 'Altres activitats extraordinàries', 'gasto', 46],
            ['90', 'Aiguafreda', 'grupo-gasto', 50],
            ['90.1', 'Ingressos', 'ingreso', 51],
            ['90.2', 'Subministraments', 'gasto', 52],
            ['90.3', 'Manteniment', 'gasto', 53],
            ['90.4', 'Assegurances', 'gasto', 54],
            ['90.5', 'Altres', 'gasto', 55],
            ['APERTURA', 'Saldo inicial', 'disponible', 90],
        ];
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
