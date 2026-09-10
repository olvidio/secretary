<?php

declare(strict_types=1);

namespace src\arqueo\domain\services;

use src\shared\domain\value_objects\Dinero;

/**
 * Cifras invertidas («capuchinos»): si la diferencia es múltiplo de 9,
 * suele ser un intercambio de dos dígitos o un corrimiento de la coma.
 */
final class DetectarCapuchinos
{
    public function diferenciaCandidata(int $diferenciaCents): bool
    {
        $d = abs($diferenciaCents);

        return $d > 0 && $d % 9 === 0;
    }

    /**
     * Importes (céntimos) que, frente al anotado, explican la diferencia.
     *
     * @return list<int>
     */
    public function alternativasQueExplican(int $importeCents, int $diferenciaCents): array
    {
        $objetivo = abs($diferenciaCents);
        if (!$this->diferenciaCandidata($objetivo)) {
            return [];
        }
        $base = abs($importeCents);
        $out = [];
        foreach ($this->variantes($base) as $otro) {
            if ($otro !== $base && abs($otro - $base) === $objetivo) {
                $out[] = $otro;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<int> */
    private function variantes(int $cents): array
    {
        $out = [];
        $s = str_replace('.', '', Dinero::fromCents($cents)->toString());
        if (str_starts_with($s, '-')) {
            $s = substr($s, 1);
        }
        $chars = str_split($s);
        $n = count($chars);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($chars[$i] === $chars[$j]) {
                    continue;
                }
                $swap = $chars;
                $tmp = $swap[$i];
                $swap[$i] = $swap[$j];
                $swap[$j] = $tmp;
                $out[] = (int) implode('', $swap);
            }
        }
        if ($cents % 10 === 0) {
            $out[] = intdiv($cents, 10);
        }
        if ($cents <= intdiv(PHP_INT_MAX, 10)) {
            $out[] = $cents * 10;
        }

        return $out;
    }
}
