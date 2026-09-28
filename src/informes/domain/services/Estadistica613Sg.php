<?php

declare(strict_types=1);

namespace src\informes\domain\services;

use src\shared\domain\value_objects\Dinero;

/** Pie del 613 de un centro sg: nº de s, aportaciones ordinarias y su media. */
final class Estadistica613Sg
{
    /**
     * @return array{
     *   num_s_previsto:int,
     *   num_s:int,
     *   aportaciones_previsto:int,
     *   aportaciones:int,
     *   aportaciones_pct:?float,
     *   media_prevista:string,
     *   media_prevista_es:string,
     *   media:string,
     *   media_es:string,
     *   media_pct:?float,
     *   sin_aportacion:int
     * }
     */
    public static function armar(
        int $numSPrevisto,
        int $numS,
        int $aportaciones,
        int $sinAportacion,
        Dinero $ordinariaAnual,
        Dinero $ordinariaRealizada,
        int $meses,
    ): array {
        $numSPrevisto = max(0, $numSPrevisto);
        $meses = max(0, $meses);
        $acumPrev = $numSPrevisto * $meses;
        $mediaPrev = self::dividir($ordinariaAnual, 12 * $numSPrevisto);
        $media = self::dividir($ordinariaRealizada, max(0, $aportaciones));

        return [
            'num_s_previsto' => $numSPrevisto,
            'num_s' => max(0, $numS),
            'aportaciones_previsto' => $acumPrev,
            'aportaciones' => max(0, $aportaciones),
            'aportaciones_pct' => $acumPrev > 0 ? $aportaciones / $acumPrev : null,
            'media_prevista' => $mediaPrev->toString(),
            'media_prevista_es' => $mediaPrev->formatEs(),
            'media' => $media->toString(),
            'media_es' => $media->formatEs(),
            'media_pct' => $mediaPrev->isZero() ? null : (float) $media->toString() / (float) $mediaPrev->toString(),
            'sin_aportacion' => max(0, $sinAportacion),
        ];
    }

    private static function dividir(Dinero $importe, int $divisor): Dinero
    {
        if ($divisor <= 0) {
            return Dinero::zero();
        }
        $cents = (int) round(((float) $importe->toString()) * 100 / $divisor);

        return Dinero::fromCents($cents);
    }
}
