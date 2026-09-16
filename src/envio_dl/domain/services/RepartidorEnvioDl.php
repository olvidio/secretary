<?php

declare(strict_types=1);

namespace src\envio_dl\domain\services;

use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

/**
 * Reparte un importe fijo entre personas no exentas con saldo >= 0, proporcional
 * al saldo de su cuenta personal (P/9) y redondeado a euros enteros (sin céntimos).
 * Saldo negativo: queda fuera. Saldo cero: reparto equitativo entre todos los elegibles.
 * Solo saldo positivo en todos: reparto proporcional al saldo.
 *
 * @phpstan-type PersonaIn array{persona:Persona, peso_cents:int}
 * @phpstan-type LineaOut array{persona_id:int, importe_cents:int}
 */
final class RepartidorEnvioDl
{
    /**
     * @param list<Persona> $personas
     * @param array<int, int> $saldoPorPersona persona_id => saldo_cents (P/9, con signo)
     * @return list<LineaOut>
     */
    public static function repartir(Dinero $total, array $personas, int $mes, array $saldoPorPersona): array
    {
        if ($total->isZero() || $total->isNegative()) {
            return [];
        }
        $elegibles = self::filtrarPersonas($personas, $mes);
        if ($elegibles === []) {
            return [];
        }
        $entrada = [];
        foreach ($elegibles as $persona) {
            if ($persona->id === null) {
                continue;
            }
            $saldo = $saldoPorPersona[$persona->id] ?? 0;
            if ($saldo < 0) {
                continue;
            }
            $entrada[] = [
                'persona' => $persona,
                'peso_cents' => $saldo,
            ];
        }
        if ($entrada === []) {
            return [];
        }

        $totalCents = $total->toCents();
        foreach ($entrada as $fila) {
            if ($fila['peso_cents'] === 0) {
                return self::repartirIgualEnEuros($totalCents, $entrada);
            }
        }

        return self::repartirProporcional($totalCents, $entrada);
    }

    /**
     * @param list<Persona> $personas
     * @return list<Persona>
     */
    public static function filtrarPersonas(array $personas, int $mes): array
    {
        $out = [];
        foreach ($personas as $p) {
            if (!$p->activo) {
                continue;
            }
            if ($p->exentaEnMes($mes)) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    /**
     * @param list<PersonaIn> $entrada
     * @return list<LineaOut>
     */
    private static function repartirProporcional(int $totalCents, array $entrada): array
    {
        $sumPeso = 0;
        foreach ($entrada as $fila) {
            $sumPeso += $fila['peso_cents'];
        }

        $asignaciones = [];
        $asignado = 0;
        $restos = [];
        foreach ($entrada as $i => $fila) {
            $exacto = (int) floor($totalCents * $fila['peso_cents'] / $sumPeso);
            $euros = intdiv($exacto, 100) * 100;
            $asignaciones[$i] = $euros;
            $asignado += $euros;
            $restos[$i] = $exacto - $euros;
        }
        $pendiente = $totalCents - $asignado;
        while ($pendiente >= 100) {
            $mejor = null;
            $maxResto = -1;
            foreach ($restos as $i => $resto) {
                if ($resto > $maxResto) {
                    $maxResto = $resto;
                    $mejor = $i;
                }
            }
            if ($mejor === null) {
                break;
            }
            $asignaciones[$mejor] += 100;
            $restos[$mejor] = 0;
            $pendiente -= 100;
        }

        $out = [];
        foreach ($entrada as $i => $fila) {
            $persona = $fila['persona'];
            if ($persona->id === null) {
                continue;
            }
            $cents = $asignaciones[$i];
            if ($cents <= 0) {
                continue;
            }
            $out[] = [
                'persona_id' => $persona->id,
                'importe_cents' => $cents,
            ];
        }

        return $out;
    }

    /**
     * @param list<PersonaIn> $entrada
     * @return list<LineaOut>
     */
    private static function repartirIgualEnEuros(int $totalCents, array $entrada): array
    {
        $n = count($entrada);
        if ($n === 0) {
            return [];
        }
        $eurosTotales = intdiv($totalCents, 100);
        $baseEuros = intdiv($eurosTotales, $n);
        $sobran = $eurosTotales - ($baseEuros * $n);
        $out = [];
        foreach ($entrada as $i => $fila) {
            $persona = $fila['persona'];
            if ($persona->id === null) {
                continue;
            }
            $euros = $baseEuros + ($i < $sobran ? 1 : 0);
            if ($euros <= 0) {
                continue;
            }
            $out[] = [
                'persona_id' => $persona->id,
                'importe_cents' => $euros * 100,
            ];
        }

        return $out;
    }
}
