<?php

declare(strict_types=1);

namespace src\informes\domain\services;

use src\shared\domain\value_objects\Dinero;

/**
 * Contrapartida P/21 ↔ G/11 para quienes aportan vivienda a generales.
 */
final class CuadreViviendaGenerales
{
    /**
     * @param list<array<string, mixed>> $apuntesP21
     * @param list<array<string, mixed>> $apuntesG11
     * @param list<array{iniciales:string,nombre:string,aporta:bool}> $personas
     * @return array<string, mixed>
     */
    public function ejecutar(array $apuntesP21, array $apuntesG11, array $personas): array
    {
        $sumP = $this->sumaOrigenA($apuntesP21);
        $sumG = $this->sumaOrigenA($apuntesG11);
        $porPersona = [];
        $totalP = 0;
        $totalG = 0;
        $ok = true;
        $avisos = [];

        foreach ($personas as $p) {
            $ini = strtolower($p['iniciales']);
            $p21 = $sumP[$ini] ?? 0;
            $g11 = $sumG[$ini] ?? 0;
            unset($sumP[$ini], $sumG[$ini]);
            if ($p['aporta']) {
                $totalP += $p21;
                $totalG += $g11;
                $dif = $p21 - $g11;
                $filaOk = $dif === 0;
                if (!$filaOk) {
                    $ok = false;
                }
                $porPersona[] = $this->filaPersona($p, $p21, $g11, $dif, $filaOk);
            } else {
                if ($g11 !== 0) {
                    $ok = false;
                    $avisos[] = $p['nombre'] . ' (' . $p['iniciales'] . ') no aporta vivienda a generales '
                        . 'pero tiene entradas G/11 de ' . $this->fmtEs($g11) . ' €.';
                }
                if ($p21 !== 0 || $g11 !== 0) {
                    $porPersona[] = $this->filaPersona($p, $p21, $g11, $p21 - $g11, $g11 === 0);
                }
            }
        }

        foreach ($sumG as $ini => $g11) {
            if ($g11 === 0) {
                continue;
            }
            $ok = false;
            $etiqueta = $ini === '' ? 'sin iniciales' : $ini;
            $avisos[] = 'Hay G/11 de ' . $this->fmtEs($g11) . ' € a nombre de ' . $etiqueta
                . ' sin persona que aporte vivienda a generales.';
            $p21 = $sumP[$ini] ?? 0;
            unset($sumP[$ini]);
            $porPersona[] = $this->filaPersona(
                ['iniciales' => $ini, 'nombre' => $etiqueta, 'aporta' => false],
                $p21,
                $g11,
                $p21 - $g11,
                false,
            );
        }

        foreach ($sumP as $ini => $p21) {
            if ($p21 === 0) {
                continue;
            }
            $etiqueta = $ini === '' ? 'sin iniciales' : $ini;
            $avisos[] = 'Hay P/21 de ' . $this->fmtEs($p21) . ' € a nombre de ' . $etiqueta
                . ' sin persona en Nombres.';
        }

        return [
            'ok' => $ok && $totalP === $totalG,
            'total_p21' => $this->fmt($totalP),
            'total_p21_es' => $this->fmtEs($totalP),
            'total_g11' => $this->fmt($totalG),
            'total_g11_es' => $this->fmtEs($totalG),
            'diferencia_es' => $this->fmtEs($totalP - $totalG),
            'por_persona' => $porPersona,
            'avisos' => $avisos,
        ];
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    private function filaPersona(array $p, int $p21, int $g11, int $dif, bool $ok): array
    {
        return [
            'iniciales' => $p['iniciales'],
            'nombre' => $p['nombre'],
            'aporta' => $p['aporta'],
            'p21_es' => $this->fmtEs($p21),
            'g11_es' => $this->fmtEs($g11),
            'diferencia_es' => $this->fmtEs($dif),
            'ok' => $ok,
        ];
    }

    /**
     * @param list<array<string, mixed>> $apuntes
     * @return array<string, int>
     */
    private function sumaOrigenA(array $apuntes): array
    {
        $out = [];
        foreach ($apuntes as $a) {
            if (strtoupper((string) ($a['origen'] ?? '')) !== 'A') {
                continue;
            }
            $ini = strtolower(trim((string) ($a['iniciales'] ?? '')));
            $cents = Dinero::fromInput((string) ($a['cantidad'] ?? '0'))->toCents();
            $out[$ini] = ($out[$ini] ?? 0) + $cents;
        }

        return $out;
    }

    private function fmt(int $cents): string
    {
        return Dinero::fromCents($cents)->toString();
    }

    private function fmtEs(int $cents): string
    {
        return Dinero::fromCents($cents)->formatEs();
    }
}
