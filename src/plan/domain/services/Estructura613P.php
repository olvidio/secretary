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
     * @return list<array{codigo:string,etiqueta:string,codigos:list<string>}>
     */
    public static function construir(array $partidasLabores): array
    {
        $fijas = [
            ['codigo' => '111', 'etiqueta' => '1. Trabajo', 'codigos' => ['111']],
            ['codigo' => '112', 'etiqueta' => '2. Familia', 'codigos' => ['112']],
            ['codigo' => '113', 'etiqueta' => '3. Varios', 'codigos' => ['113']],
            ['codigo' => '12', 'etiqueta' => '2. Extraordinarios', 'codigos' => ['12']],
            ['codigo' => '21', 'etiqueta' => 'Vivienda', 'codigos' => ['21']],
            ['codigo' => '22', 'etiqueta' => 'Ordinarios', 'codigos' => ['22']],
            ['codigo' => '23', 'etiqueta' => 'Ropa', 'codigos' => ['23']],
            ['codigo' => '24', 'etiqueta' => 'ca, crt, cv', 'codigos' => ['24']],
            ['codigo' => '25', 'etiqueta' => 'Médicos', 'codigos' => ['25']],
            ['codigo' => '26', 'etiqueta' => 'Coche, viajes', 'codigos' => ['26']],
            ['codigo' => '27', 'etiqueta' => 'Estudios y otros', 'codigos' => ['27']],
            ['codigo' => '28', 'etiqueta' => 'Obligaciones económicas', 'codigos' => ['28']],
            ['codigo' => '4', 'etiqueta' => 'Ayudas familiares', 'codigos' => ['4']],
            ['codigo' => '51', 'etiqueta' => 'Atención sacerdotal de mujeres', 'codigos' => ['51']],
            ['codigo' => '52', 'etiqueta' => 'Atención crt, cv y otras actividades', 'codigos' => ['52']],
            ['codigo' => '6', 'etiqueta' => 'Necesidades de la sede', 'codigos' => ['6']],
        ];
        foreach ($partidasLabores as $p) {
            $fijas[] = [
                'codigo' => $p['codigo'],
                'etiqueta' => $p['etiqueta'],
                'codigos' => [$p['codigo']],
            ];
        }
        $fijas[] = ['codigo' => '9', 'etiqueta' => 'Saldo en las c/c personales', 'codigos' => ['9']];

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
