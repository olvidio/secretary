<?php

declare(strict_types=1);

namespace src\presupuestos\domain\services;

/**
 * Agrupa las líneas de previsión P en capítulos del 613 (cuentas padre)
 * e inserta III. Disponible y VIII. Saldo final.
 */
final class AgrupadorPrevision613P
{
    /** @var array<string, string> */
    public const ETIQUETAS = [
        'I' => 'I. Ingresos',
        'II' => 'II. Gastos personales',
        'III' => 'III. Disponible',
        'IV' => 'IV. Ayudas familiares',
        'V' => 'V. Atención labores',
        'VI' => 'VI. Necesidades de la sede',
        'VII' => 'VII. Otras labores apostólicas',
        'VIII' => 'VIII. Saldo final',
        'IX' => 'IX. Saldo en las c/c personales',
    ];

    /**
     * @param list<array{codigo:string,etiqueta:string,grupo?:string,total_cents:int,personas_cents:list<int>}> $lineas
     * @return list<array{tipo:string,grupo:string,codigo:?string,etiqueta:string,total_cents:int,personas_cents:list<int>}>
     */
    public static function filas(array $lineas): array
    {
        $cubos = [];
        foreach ($lineas as $l) {
            $grupo = (string) ($l['grupo'] ?? '');
            if ($grupo === '') {
                $grupo = self::grupoDe((string) $l['codigo']);
            }
            $cubos[$grupo][] = $l;
        }
        $out = [];
        $sumas = [];
        foreach (['I', 'II'] as $id) {
            $hijos = $cubos[$id] ?? [];
            $sumas[$id] = self::sumar($hijos);
            $out = array_merge($out, self::bloque($id, $hijos, $sumas[$id]));
        }
        $sumas['III'] = self::restar($sumas['I'], $sumas['II']);
        $out[] = self::filaTotal('III', $sumas['III']);

        foreach (['IV', 'V', 'VI', 'VII'] as $id) {
            $hijos = $cubos[$id] ?? [];
            $sumas[$id] = self::sumar($hijos);
            $out = array_merge($out, self::bloque($id, $hijos, $sumas[$id]));
        }
        $sumas['VIII'] = $sumas['III'];
        foreach (['IV', 'V', 'VI', 'VII'] as $id) {
            $sumas['VIII'] = self::restar($sumas['VIII'], $sumas[$id]);
        }
        $out[] = self::filaTotal('VIII', $sumas['VIII']);
        $out = array_merge($out, self::bloque('IX', $cubos['IX'] ?? [], self::sumar($cubos['IX'] ?? [])));

        return $out;
    }

    public static function grupoDe(string $codigo): string
    {
        if (in_array($codigo, ['111', '112', '113', '12'], true)) {
            return 'I';
        }
        if (in_array($codigo, ['21', '212', '22', '23', '24', '25', '26', '27', '28'], true)) {
            return 'II';
        }
        if ($codigo === '4') {
            return 'IV';
        }
        if (in_array($codigo, ['51', '52'], true)) {
            return 'V';
        }
        if ($codigo === '6') {
            return 'VI';
        }
        if ($codigo === '9') {
            return 'IX';
        }
        if (preg_match('/^7[1-9]$/', $codigo) === 1) {
            return 'VII';
        }

        return '';
    }

    /**
     * @param list<array{codigo:string,etiqueta:string,total_cents:int,personas_cents:list<int>}> $hijos
     * @param array{0:int,1:list<int>} $suma
     * @return list<array{tipo:string,grupo:string,codigo:?string,etiqueta:string,total_cents:int,personas_cents:list<int>}>
     */
    private static function bloque(string $id, array $hijos, array $suma): array
    {
        $etiqueta = self::ETIQUETAS[$id] ?? $id;
        if ($hijos === []) {
            return [];
        }
        if (count($hijos) === 1) {
            $h = $hijos[0];

            return [[
                'tipo' => 'padre',
                'grupo' => $id,
                'codigo' => (string) $h['codigo'],
                'etiqueta' => $etiqueta,
                'total_cents' => $h['total_cents'],
                'personas_cents' => $h['personas_cents'],
            ]];
        }
        $out = [[
            'tipo' => 'padre',
            'grupo' => $id,
            'codigo' => null,
            'etiqueta' => $etiqueta,
            'total_cents' => $suma[0],
            'personas_cents' => $suma[1],
        ]];
        foreach ($hijos as $h) {
            $out[] = [
                'tipo' => 'hijo',
                'grupo' => $id,
                'codigo' => (string) $h['codigo'],
                'etiqueta' => (string) $h['etiqueta'],
                'total_cents' => $h['total_cents'],
                'personas_cents' => $h['personas_cents'],
            ];
        }

        return $out;
    }

    /**
     * @param array{0:int,1:list<int>} $suma
     * @return array{tipo:string,grupo:string,codigo:?string,etiqueta:string,total_cents:int,personas_cents:list<int>}
     */
    private static function filaTotal(string $id, array $suma): array
    {
        return [
            'tipo' => 'total',
            'grupo' => $id,
            'codigo' => null,
            'etiqueta' => self::ETIQUETAS[$id],
            'total_cents' => $suma[0],
            'personas_cents' => $suma[1],
        ];
    }

    /**
     * @param list<array{total_cents:int,personas_cents:list<int>}> $filas
     * @return array{0:int,1:list<int>}
     */
    private static function sumar(array $filas): array
    {
        $total = 0;
        $personas = [];
        foreach ($filas as $f) {
            $total += $f['total_cents'];
            foreach ($f['personas_cents'] as $i => $cents) {
                $personas[$i] = ($personas[$i] ?? 0) + $cents;
            }
        }

        return [$total, array_values($personas)];
    }

    /**
     * @param array{0:int,1:list<int>} $a
     * @param array{0:int,1:list<int>} $b
     * @return array{0:int,1:list<int>}
     */
    private static function restar(array $a, array $b): array
    {
        $n = max(count($a[1]), count($b[1]));
        $personas = [];
        for ($i = 0; $i < $n; $i++) {
            $personas[] = ($a[1][$i] ?? 0) - ($b[1][$i] ?? 0);
        }

        return [$a[0] - $b[0], $personas];
    }
}
