<?php

declare(strict_types=1);

namespace src\informes\domain\services;

use DateTimeImmutable;

/**
 * Personas sin apunte (no cierre) en algún mes del período, salvo exención.
 */
final class MesesSinMovimiento
{
    /** @var array<int, string> */
    private const MESES_ES = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre',
    ];

    /**
     * @param list<array{iniciales:string,nombre:string,exento_meses:list<int>}> $personas
     * @param list<array<string, mixed>> $apuntes
     * @return array<string, mixed>
     */
    public function ejecutar(array $personas, array $apuntes, DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $meses = $this->mesesDelPeriodo($desde, $hasta);
        $hechos = $this->mesesConMovimiento($apuntes);
        $porPersona = [];
        $ok = true;

        foreach ($personas as $p) {
            $ini = strtolower(trim($p['iniciales']));
            if ($ini === '') {
                continue;
            }
            $exentos = [];
            foreach ($p['exento_meses'] as $m) {
                $exentos[(int) $m] = true;
            }
            $faltan = [];
            $faltanEs = [];
            foreach ($meses as $ym) {
                $mesNum = (int) substr($ym, 5, 2);
                if (isset($exentos[$mesNum])) {
                    continue;
                }
                if (!empty($hechos[$ini][$ym])) {
                    continue;
                }
                $faltan[] = $ym;
                $faltanEs[] = $this->etiquetaMes($ym, $meses);
            }
            if ($faltan === []) {
                continue;
            }
            $ok = false;
            $porPersona[] = [
                'iniciales' => $p['iniciales'],
                'nombre' => $p['nombre'],
                'meses' => $faltan,
                'meses_es' => $faltanEs,
            ];
        }

        return [
            'ok' => $ok,
            'meses' => $meses,
            'por_persona' => $porPersona,
        ];
    }

    /**
     * @param list<string> $mesesPeriodo
     */
    private function etiquetaMes(string $ym, array $mesesPeriodo): string
    {
        $anio = (int) substr($ym, 0, 4);
        $mes = (int) substr($ym, 5, 2);
        $nombre = self::MESES_ES[$mes] ?? $ym;
        $variosAnios = $mesesPeriodo !== []
            && substr($mesesPeriodo[0], 0, 4) !== substr($mesesPeriodo[array_key_last($mesesPeriodo)], 0, 4);
        if ($variosAnios) {
            return $nombre . ' ' . $anio;
        }

        return $nombre;
    }

    /**
     * @return list<string>
     */
    private function mesesDelPeriodo(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $cur = $desde->modify('first day of this month')->setTime(0, 0);
        $end = $hasta->modify('first day of this month')->setTime(0, 0);
        if ($cur > $end) {
            return [];
        }
        $out = [];
        while ($cur <= $end) {
            $out[] = $cur->format('Y-m');
            $cur = $cur->modify('+1 month');
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $apuntes
     * @return array<string, array<string, true>>
     */
    private function mesesConMovimiento(array $apuntes): array
    {
        $out = [];
        foreach ($apuntes as $a) {
            if (!empty($a['es_cierre'])) {
                continue;
            }
            $concepto = (string) ($a['concepto_codigo'] ?? '');
            if ($concepto === '32') {
                continue;
            }
            $ini = strtolower(trim((string) ($a['iniciales'] ?? '')));
            if ($ini === '') {
                continue;
            }
            $fecha = (string) ($a['fecha'] ?? '');
            if (preg_match('/^(\d{4}-\d{2})/', $fecha, $m) !== 1) {
                continue;
            }
            $out[$ini][$m[1]] = true;
        }

        return $out;
    }
}
