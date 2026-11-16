<?php

declare(strict_types=1);

namespace src\apuntes\domain\services;

use src\shared\domain\value_objects\Dinero;

/** Saldo de cuadre (gastos − ingresos) de apuntes P por persona y origen A/B/C. */
final class SaldoCuadreApuntes
{
    /**
     * @param array<string, string> $naturalezas codigo => naturaleza
     */
    public static function signedCents(string $codigo, string $cantidad, array $naturalezas): int
    {
        $nat = $naturalezas[$codigo] ?? '';
        if ($nat !== 'gasto' && $nat !== 'ingreso') {
            return 0;
        }
        $cents = Dinero::fromInput($cantidad)->toCents();

        return $nat === 'gasto' ? $cents : -$cents;
    }

    /**
     * Signo de una fila del listado en el cuadre. La línea «9» que cubre con la
     * c/c el déficit de una remesa (cantidad negativa) compensa ese exceso de gastos.
     *
     * @param array<string, mixed> $apunte
     * @param array<string, string> $naturalezas
     */
    public static function signedFila(array $apunte, array $naturalezas): int
    {
        if (!empty($apunte['contrapartida_cc'])) {
            return Dinero::fromInput((string) ($apunte['cantidad'] ?? '0'))->toCents();
        }

        return self::signedCents(
            (string) ($apunte['concepto_codigo'] ?? ''),
            (string) ($apunte['cantidad'] ?? '0'),
            $naturalezas,
        );
    }

    /**
     * @param list<array<string, mixed>> $apuntes
     * @param array<string, string> $naturalezas
     * @param array<string, int> $personaIdPorIniciales iniciales en minúsculas => persona_id
     * @return array<int, array{A: int, B: int, C: int}>
     */
    public static function porPersonaYOrigen(
        array $apuntes,
        array $naturalezas,
        array $personaIdPorIniciales,
    ): array {
        $out = [];
        foreach ($apuntes as $apunte) {
            if (!empty($apunte['es_cierre'])) {
                continue;
            }
            if (strtoupper((string) ($apunte['cuenta'] ?? '')) !== 'P') {
                continue;
            }
            $origen = strtoupper((string) ($apunte['origen'] ?? ''));
            if (!in_array($origen, ['A', 'B', 'C'], true)) {
                continue;
            }
            $ini = strtolower(trim((string) ($apunte['iniciales'] ?? '')));
            if ($ini === '' || !isset($personaIdPorIniciales[$ini])) {
                continue;
            }
            $pid = $personaIdPorIniciales[$ini];
            $signed = self::signedFila($apunte, $naturalezas);
            if (!isset($out[$pid])) {
                $out[$pid] = ['A' => 0, 'B' => 0, 'C' => 0];
            }
            $out[$pid][$origen] += $signed;
        }

        return $out;
    }
}
