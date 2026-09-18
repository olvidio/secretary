<?php

declare(strict_types=1);

namespace src\importacion\domain\services;

/**
 * En el Excel legado, una exención de 1 a 12 marca a quien no participa en el
 * cierre de vivienda. En Secretario eso es «aporta G» = no, con la exención vacía.
 */
final class InterpretarExencionExcel
{
    /**
     * @return array{
     *   mesExentoInicio: ?int,
     *   mesExentoFin: ?int,
     *   mesExento2Inicio: ?int,
     *   mesExento2Fin: ?int,
     *   aportaGenerales: bool
     * }
     */
    public static function de(
        ?int $exentoInicio,
        ?int $exentoFin,
        ?int $exento2Inicio,
        ?int $exento2Fin,
        bool $aportaPorDefecto,
    ): array {
        if ($exentoInicio === 1 && $exentoFin === 12) {
            return [
                'mesExentoInicio' => null,
                'mesExentoFin' => null,
                'mesExento2Inicio' => null,
                'mesExento2Fin' => null,
                'aportaGenerales' => false,
            ];
        }

        return [
            'mesExentoInicio' => $exentoInicio,
            'mesExentoFin' => $exentoFin,
            'mesExento2Inicio' => $exento2Inicio,
            'mesExento2Fin' => $exento2Fin,
            'aportaGenerales' => $aportaPorDefecto,
        ];
    }
}
