<?php

declare(strict_types=1);

namespace src\plan\domain\services;

/**
 * Estructura del 613 P: capítulos fijos + cap. VII según partidas del centro/plan.
 */
final class Estructura613P
{
    /**
     * @param list<array{codigo:string,etiqueta:string}> $partidasLabores
     * @return list<array{codigo:string,etiqueta:string,codigos:list<string>,grupo:string}>
     */
    public static function construir(array $partidasLabores): array
    {
        $fijas = [
            ['codigo' => '111', 'etiqueta' => '1. Trabajo', 'codigos' => ['111'], 'grupo' => 'I'],
            ['codigo' => '112', 'etiqueta' => '2. Familia', 'codigos' => ['112'], 'grupo' => 'I'],
            ['codigo' => '113', 'etiqueta' => '3. Varios', 'codigos' => ['113'], 'grupo' => 'I'],
            ['codigo' => '12', 'etiqueta' => '2. Extraordinarios', 'codigos' => ['12'], 'grupo' => 'I'],
            ['codigo' => '211', 'etiqueta' => 'Vivienda general', 'codigos' => ['211'], 'grupo' => 'II'],
            ['codigo' => '212', 'etiqueta' => 'Vivienda personal', 'codigos' => ['212'], 'grupo' => 'II'],
            ['codigo' => '22', 'etiqueta' => 'Ordinarios', 'codigos' => ['22'], 'grupo' => 'II'],
            ['codigo' => '23', 'etiqueta' => 'Ropa', 'codigos' => ['23'], 'grupo' => 'II'],
            ['codigo' => '24', 'etiqueta' => 'ca, crt, cv', 'codigos' => ['24'], 'grupo' => 'II'],
            ['codigo' => '25', 'etiqueta' => 'Médicos', 'codigos' => ['25'], 'grupo' => 'II'],
            ['codigo' => '26', 'etiqueta' => 'Coche, viajes', 'codigos' => ['26'], 'grupo' => 'II'],
            ['codigo' => '27', 'etiqueta' => 'Estudios y otros', 'codigos' => ['27'], 'grupo' => 'II'],
            ['codigo' => '28', 'etiqueta' => 'Obligaciones económicas', 'codigos' => ['28'], 'grupo' => 'II'],
            ['codigo' => '4', 'etiqueta' => 'Ayudas familiares', 'codigos' => ['4'], 'grupo' => 'IV'],
            ['codigo' => '51', 'etiqueta' => 'Atención sacerdotal de mujeres', 'codigos' => ['51'], 'grupo' => 'V'],
            ['codigo' => '52', 'etiqueta' => 'Atención crt, cv y otras actividades', 'codigos' => ['52'], 'grupo' => 'V'],
            ['codigo' => '6', 'etiqueta' => 'Necesidades de la sede', 'codigos' => ['6'], 'grupo' => 'VI'],
        ];
        foreach ($partidasLabores as $p) {
            $fijas[] = [
                'codigo' => $p['codigo'],
                'etiqueta' => $p['etiqueta'],
                'codigos' => [$p['codigo']],
                'grupo' => 'VII',
            ];
        }
        $fijas[] = ['codigo' => '9', 'etiqueta' => 'Saldo en las c/c personales', 'codigos' => ['9'], 'grupo' => 'IX'];

        return $fijas;
    }

    /**
     * @param list<array{codigo:string,etiqueta:string}> $partidasLabores
     * @return list<string>
     */
    public static function codigosLabores(array $partidasLabores): array
    {
        return array_map(static fn (array $p): string => $p['codigo'], $partidasLabores);
    }
}
