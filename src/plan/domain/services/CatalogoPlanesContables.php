<?php

declare(strict_types=1);

namespace src\plan\domain\services;

/**
 * Plan contable H16n. Los capítulos I–VI y VIII–IX son comunes; el cap. VII
 * (Otras labores apostólicas) es configurable por centro.
 */
final class CatalogoPlanesContables
{
    public const H16N = 'H16n';

    /** @return list<array{codigo:string,nombre:string}> */
    public static function todos(): array
    {
        return [
            ['codigo' => self::H16N, 'nombre' => 'H16n'],
        ];
    }

    public static function esValido(string $codigo): bool
    {
        return $codigo === self::H16N;
    }

    /** @return list<string> */
    public static function codigosCapituloVIIReservados(): array
    {
        return ['71', '72', '73', '74', '75', '76', '77', '78', '79'];
    }

    /**
     * Catálogo de referencia para las partidas del cap. VII (71–79).
     *
     * @return list<array{codigo:string,etiqueta:string,orden:int}>
     */
    public static function partidasLaboresReferencia(): array
    {
        return [
            ['codigo' => '71', 'etiqueta' => 'Necesidades generales', 'orden' => 10],
            ['codigo' => '72', 'etiqueta' => 'Fundació Montseny', 'orden' => 20],
            ['codigo' => '73', 'etiqueta' => 'Fundació Proas', 'orden' => 30],
            ['codigo' => '74', 'etiqueta' => 'Prelatura', 'orden' => 40],
            ['codigo' => '75', 'etiqueta' => 'Associació Montroig', 'orden' => 50],
            ['codigo' => '76', 'etiqueta' => 'Associació Assitència i Salut', 'orden' => 60],
            ['codigo' => '77', 'etiqueta' => 'Proico', 'orden' => 70],
            ['codigo' => '78', 'etiqueta' => 'Casa Escrivá', 'orden' => 80],
            ['codigo' => '79', 'etiqueta' => 'Otras labores', 'orden' => 90],
        ];
    }

    /**
     * Semilla por defecto en centros nuevos: las seis primeras (71–76).
     *
     * @return list<array{codigo:string,etiqueta:string,orden:int}>
     */
    public static function partidasLaboresPorDefecto(): array
    {
        return array_slice(self::partidasLaboresReferencia(), 0, 6);
    }

    /**
     * Backfill de centros ya existentes antes de H16n: las nueve partidas (71–79).
     *
     * @return list<array{codigo:string,etiqueta:string,orden:int}>
     */
    public static function partidasLaboresLegacy(): array
    {
        return self::partidasLaboresReferencia();
    }
}
