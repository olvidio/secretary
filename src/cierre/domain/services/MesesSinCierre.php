<?php

declare(strict_types=1);

namespace src\cierre\domain\services;

use DateTimeImmutable;

/**
 * Meses anteriores que aún requieren cierre (reparto pendiente tras ignorar
 * cierres automáticos ya generados, completos o parciales).
 */
final class MesesSinCierre
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
     * @param list<string> $mesesRevisar Y-m desde fecha de inicio hasta el mes anterior al de cierre
     * @param array<string, bool> $mesesQueRequieren
     * @param array<string, string> $gastosEs
     * @return array{ok: bool, meses: list<array{ym: string, mes: int, mes_es: string, gastos_es: string}>}
     */
    public function ejecutar(
        array $mesesRevisar,
        array $mesesQueRequieren,
        array $gastosEs,
    ): array {
        $faltantes = [];
        foreach ($mesesRevisar as $ym) {
            if (empty($mesesQueRequieren[$ym])) {
                continue;
            }
            $mes = (int) substr($ym, 5, 2);
            $faltantes[] = [
                'ym' => $ym,
                'mes' => $mes,
                'mes_es' => $this->etiquetaMes($ym, $mesesRevisar),
                'gastos_es' => $gastosEs[$ym] ?? '0,00',
            ];
        }

        return [
            'ok' => $faltantes === [],
            'meses' => $faltantes,
        ];
    }

    /**
     * @return list<string>
     */
    public static function mesesAnterioresAlCierre(
        DateTimeImmutable $fechaInicio,
        DateTimeImmutable $fechaCierre,
    ): array {
        $cur = $fechaInicio->modify('first day of this month')->setTime(0, 0);
        $limite = $fechaCierre->modify('first day of this month')->modify('-1 month')->setTime(0, 0);
        if ($cur > $limite) {
            return [];
        }
        $out = [];
        while ($cur <= $limite) {
            $out[] = $cur->format('Y-m');
            $cur = $cur->modify('+1 month');
        }

        return $out;
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
}
