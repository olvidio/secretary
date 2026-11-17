<?php

declare(strict_types=1);

namespace src\personal\domain\services;

/**
 * Criterios del buscador de movimientos del libro personal, aplicados a las
 * filas que ya devuelve el listado. Sin I/O: el rango de fechas lo resuelve
 * quien carga las filas; aquí solo se descartan las que no encajan y se suman.
 */
final class FiltroMovimientosPersonales
{
    /**
     * @param list<int> $categoriaIds cuentas admitidas (la elegida y sus subcuentas); vacío = todas
     */
    public function __construct(
        public readonly string $sentido = '',
        public readonly array $categoriaIds = [],
        public readonly string $tesoreria = '',
        public readonly string $texto = '',
        public readonly ?int $minCents = null,
        public readonly ?int $maxCents = null,
    ) {
    }

    /**
     * @template T of array<string, mixed>
     * @param list<T> $filas
     * @return list<T>
     */
    public function aplicar(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            if ($this->pasa($fila)) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return array{ingresos:int, gastos:int, neto:int, numero:int}
     */
    public static function totales(array $filas): array
    {
        $ingresos = 0;
        $gastos = 0;
        foreach ($filas as $fila) {
            $cents = (int) ($fila['cents'] ?? 0);
            if (($fila['sentido'] ?? '') === 'ingreso') {
                $ingresos += $cents;
            } elseif (($fila['sentido'] ?? '') === 'gasto') {
                $gastos += $cents;
            }
        }

        return ['ingresos' => $ingresos, 'gastos' => $gastos, 'neto' => $ingresos - $gastos, 'numero' => count($filas)];
    }

    /** @param array<string, mixed> $fila */
    private function pasa(array $fila): bool
    {
        if ($this->sentido !== '' && ($fila['sentido'] ?? '') !== $this->sentido) {
            return false;
        }
        if ($this->categoriaIds !== [] && !in_array($fila['categoria_id'] ?? null, $this->categoriaIds, true)) {
            return false;
        }
        if ($this->tesoreria !== '') {
            $t = strtoupper($this->tesoreria);
            $tes = array_map(
                static fn ($v): string => strtoupper((string) $v),
                [$fila['tesoreria'] ?? '', $fila['tesoreria_origen'] ?? '', $fila['tesoreria_destino'] ?? ''],
            );
            if (!in_array($t, $tes, true)) {
                return false;
            }
        }
        $cents = (int) ($fila['cents'] ?? 0);
        if ($this->minCents !== null && $cents < $this->minCents) {
            return false;
        }
        if ($this->maxCents !== null && $cents > $this->maxCents) {
            return false;
        }
        if ($this->texto !== '') {
            $donde = self::normalizar(($fila['nota'] ?? '') . ' ' . ($fila['categoria'] ?? '') . ' ' . ($fila['concepto_generales'] ?? ''));
            foreach (preg_split('/\s+/', self::normalizar($this->texto)) ?: [] as $palabra) {
                if ($palabra !== '' && !str_contains($donde, $palabra)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function normalizar(string $s): string
    {
        return strtr(mb_strtolower(trim($s)), [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c', '·' => '',
        ]);
    }
}
