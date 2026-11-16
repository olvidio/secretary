<?php

declare(strict_types=1);

namespace src\cierre\domain\services;

use src\shared\domain\value_objects\Dinero;

/**
 * Compara el cierre de vivienda ya generado en el mes con el que saldría ahora.
 */
final class ComparadorCierreMes
{
    /**
     * @param array<string, int> $deberia iniciales => céntimos que anotaría el cierre ahora (sin ceros)
     * @param array<string, int> $generado iniciales => céntimos de los automáticos ya anotados
     * @return array{estado: string, diferencias: list<array{iniciales: string, generado_es: string, deberia_es: string}>}
     *   estado: 'no_aplica' (no hay nada que repartir), 'ok', 'sin_generar' o 'desactualizado'
     */
    public static function comparar(array $deberia, array $generado): array
    {
        $deberia = array_filter($deberia, static fn (int $c): bool => $c !== 0);
        $generado = array_filter($generado, static fn (int $c): bool => $c !== 0);
        if ($deberia === [] && $generado === []) {
            return ['estado' => 'no_aplica', 'diferencias' => []];
        }

        $diferencias = [];
        $todas = array_unique(array_merge(array_keys($deberia), array_keys($generado)));
        sort($todas);
        foreach ($todas as $ini) {
            $d = $deberia[$ini] ?? 0;
            $g = $generado[$ini] ?? 0;
            if ($d === $g) {
                continue;
            }
            $diferencias[] = [
                'iniciales' => (string) $ini,
                'generado_es' => Dinero::fromCents($g)->formatEs(),
                'deberia_es' => Dinero::fromCents($d)->formatEs(),
            ];
        }

        if ($diferencias === []) {
            $estado = 'ok';
        } elseif ($generado === []) {
            $estado = 'sin_generar';
        } else {
            $estado = 'desactualizado';
        }

        return ['estado' => $estado, 'diferencias' => $diferencias];
    }
}
