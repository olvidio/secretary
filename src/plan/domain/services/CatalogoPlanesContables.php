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

    /** Contabilidad de un club: un solo libro, plan propio, importación Grisbi. */
    public const CLUB = 'Club';

    /** Secretario de sg: un solo libro, conceptos y 613 del Excel sg-v5. */
    public const CENTRO_SG = 'H16s';

    /** @return list<array{codigo:string,nombre:string}> */
    public static function todos(): array
    {
        return [
            ['codigo' => self::H16N, 'nombre' => 'H16n'],
            ['codigo' => self::CLUB, 'nombre' => 'Club'],
            ['codigo' => self::CENTRO_SG, 'nombre' => 'H16s'],
        ];
    }

    public static function esValido(string $codigo): bool
    {
        foreach (self::todos() as $plan) {
            if ($plan['codigo'] === $codigo) {
                return true;
            }
        }

        return false;
    }

    public static function esClub(string $codigo): bool
    {
        return $codigo === self::CLUB;
    }

    public static function esCentroSg(string $codigo): bool
    {
        return $codigo === self::CENTRO_SG;
    }

    /** @return list<string> */
    public static function codigosCapituloVIIReservados(): array
    {
        return ['71', '72', '73', '74', '75', '76', '77', '78', '79'];
    }

    /**
     * Catálogo de referencia para las partidas del cap. VII (71–79).
     *
     * @return list<array{codigo:string,etiqueta:string,orden:int,desgrava:bool}>
     */
    public static function partidasLaboresReferencia(): array
    {
        return [
            ['codigo' => '71', 'etiqueta' => 'Necesidades generales', 'orden' => 10, 'desgrava' => false],
            ['codigo' => '72', 'etiqueta' => 'Fundació Montseny', 'orden' => 20, 'desgrava' => true],
            ['codigo' => '73', 'etiqueta' => 'Fundació Proas', 'orden' => 30, 'desgrava' => true],
            ['codigo' => '74', 'etiqueta' => 'Prelatura', 'orden' => 40, 'desgrava' => false],
            ['codigo' => '75', 'etiqueta' => 'Associació Montroig', 'orden' => 50, 'desgrava' => true],
            ['codigo' => '76', 'etiqueta' => 'Associació Assitència i Salut', 'orden' => 60, 'desgrava' => true],
            ['codigo' => '77', 'etiqueta' => 'Proico', 'orden' => 70, 'desgrava' => false],
            ['codigo' => '78', 'etiqueta' => 'Casa Escrivá', 'orden' => 80, 'desgrava' => false],
            ['codigo' => '79', 'etiqueta' => 'Otras labores', 'orden' => 90, 'desgrava' => false],
        ];
    }

    /**
     * Semilla por defecto en centros nuevos: las seis primeras (71–76).
     *
     * @return list<array{codigo:string,etiqueta:string,orden:int,desgrava:bool}>
     */
    public static function partidasLaboresPorDefecto(): array
    {
        return array_slice(self::partidasLaboresReferencia(), 0, 6);
    }

    /**
     * Backfill de centros ya existentes antes de H16n: las nueve partidas (71–79).
     *
     * @return list<array{codigo:string,etiqueta:string,orden:int,desgrava:bool}>
     */
    public static function partidasLaboresLegacy(): array
    {
        return self::partidasLaboresReferencia();
    }
}
