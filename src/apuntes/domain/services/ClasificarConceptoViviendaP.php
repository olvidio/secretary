<?php

declare(strict_types=1);

namespace src\apuntes\domain\services;

use src\apuntes\domain\entity\Apunte;

/**
 * Desglose del antiguo P/21: 211 vivienda general (cuadra G/11), 212 vivienda personal.
 */
final class ClasificarConceptoViviendaP
{
    public const GENERAL = '211';
    public const PERSONAL = '212';
    public const LEGADO = '21';

    /**
     * @param list<Apunte> $apuntes
     * @return list<Apunte>
     */
    public function reclasificarApuntes(array $apuntes): array
    {
        $paresG11 = $this->indiceParesG11($apuntes);
        $out = [];
        foreach ($apuntes as $apunte) {
            $out[] = $this->reclasificarUno($apunte, $paresG11);
        }

        return $out;
    }

    /**
     * @param array<string, int> $paresG11 clave → unidades disponibles
     */
    public function reclasificarUno(Apunte $apunte, array &$paresG11): Apunte
    {
        if ($apunte->cuenta !== 'P' || $apunte->conceptoCodigo !== self::LEGADO) {
            return $apunte;
        }
        $clave = $this->clavePar($apunte);
        if ($clave !== null && ($paresG11[$clave] ?? 0) > 0) {
            --$paresG11[$clave];

            return $this->conConcepto($apunte, self::GENERAL);
        }

        return $this->conConcepto($apunte, self::PERSONAL);
    }

    /**
     * @param list<Apunte> $apuntes
     * @return array<string, int>
     */
    private function indiceParesG11(array $apuntes): array
    {
        $out = [];
        foreach ($apuntes as $apunte) {
            if ($apunte->cuenta !== 'G' || $apunte->conceptoCodigo !== '11' || strtoupper($apunte->origen) !== 'A') {
                continue;
            }
            $clave = $this->clavePar($apunte);
            if ($clave === null) {
                continue;
            }
            $out[$clave] = ($out[$clave] ?? 0) + 1;
        }

        return $out;
    }

    private function clavePar(Apunte $apunte): ?string
    {
        $ini = strtolower(trim((string) ($apunte->iniciales ?? '')));
        if ($ini === '') {
            return null;
        }

        return $apunte->fecha->format('Y-m-d')
            . '|' . $ini
            . '|' . $apunte->cantidad->toCents();
    }

    private function conConcepto(Apunte $apunte, string $concepto): Apunte
    {
        if ($apunte->conceptoCodigo === $concepto) {
            return $apunte;
        }

        return new Apunte(
            $apunte->id,
            $apunte->fecha,
            $apunte->cuenta,
            $apunte->origen,
            $apunte->iniciales,
            $concepto,
            $apunte->observaciones,
            $apunte->cantidad,
            $apunte->esCierre,
            $apunte->parId,
        );
    }
}
