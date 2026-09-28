<?php

declare(strict_types=1);

namespace src\listados\domain\services;

/**
 * Hoja «E 32» del Secretario sg: aportaciones por persona y mes.
 * Los s van por grupo (ordinaria = 11, extraordinaria = 12).
 * Los cp van aparte (ayudas = 13).
 */
final class ListadoAportacionesSg
{
    /**
     * @param list<array{id:int,nombre:string,grupo:int,clase:string}> $personas
     * @param array<int, array<string, array<int, int>>> $importes persona => código => mes (1-12) => céntimos
     * @return array{
     *   grupos: list<array{grupo:int, personas: list<array<string, mixed>>, total_ordinaria: list<int>, total_extraordinaria: list<int>}>,
     *   cp: list<array<string, mixed>>,
     *   total_cp: list<int>
     * }
     */
    public static function armar(array $personas, array $importes): array
    {
        $porGrupo = [];
        $cp = [];
        foreach ($personas as $p) {
            $clase = $p['clase'] === 'cp' ? 'cp' : ($p['clase'] === 's' ? 's' : '');
            if ($clase === '') {
                continue;
            }
            if ($clase === 'cp') {
                $cp[] = self::fila($p, $importes, '13');
                continue;
            }
            $g = $p['grupo'] > 0 ? $p['grupo'] : 1;
            $porGrupo[$g][] = $p;
        }
        ksort($porGrupo);
        $grupos = [];
        foreach ($porGrupo as $grupo => $lista) {
            usort($lista, static fn (array $a, array $b): int => strcasecmp($a['nombre'], $b['nombre']));
            $filas = [];
            $totO = array_fill(0, 12, 0);
            $totE = array_fill(0, 12, 0);
            foreach ($lista as $p) {
                $ord = self::meses($importes, $p['id'], '11');
                $ext = self::meses($importes, $p['id'], '12');
                $filas[] = [
                    'id' => $p['id'],
                    'nombre' => $p['nombre'],
                    'ordinaria' => $ord,
                    'extraordinaria' => $ext,
                ];
                $totO = self::sumar($totO, $ord);
                $totE = self::sumar($totE, $ext);
            }
            $grupos[] = [
                'grupo' => $grupo,
                'personas' => $filas,
                'total_ordinaria' => $totO,
                'total_extraordinaria' => $totE,
            ];
        }
        usort($cp, static fn (array $a, array $b): int => strcasecmp((string) $a['nombre'], (string) $b['nombre']));
        $totalCp = array_fill(0, 12, 0);
        foreach ($cp as $fila) {
            $totalCp = self::sumar($totalCp, $fila['meses']);
        }

        return [
            'grupos' => $grupos,
            'cp' => $cp,
            'total_cp' => $totalCp,
        ];
    }

    /**
     * @param array<int, array<string, array<int, int>>> $importes
     * @param array{id:int,nombre:string,grupo:int,clase:string} $p
     * @return array{id:int,nombre:string,grupo:int,meses:list<int>}
     */
    private static function fila(array $p, array $importes, string $codigo): array
    {
        return [
            'id' => $p['id'],
            'nombre' => $p['nombre'],
            'grupo' => $p['grupo'],
            'meses' => self::meses($importes, $p['id'], $codigo),
        ];
    }

    /**
     * @param array<int, array<string, array<int, int>>> $importes
     * @return list<int>
     */
    private static function meses(array $importes, int $id, string $codigo): array
    {
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[] = $importes[$id][$codigo][$m] ?? 0;
        }

        return $out;
    }

    /**
     * @param list<int> $a
     * @param list<int> $b
     * @return list<int>
     */
    private static function sumar(array $a, array $b): array
    {
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $out[] = ($a[$i] ?? 0) + ($b[$i] ?? 0);
        }

        return $out;
    }
}
