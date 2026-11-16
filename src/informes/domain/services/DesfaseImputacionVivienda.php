<?php

declare(strict_types=1);

namespace src\informes\domain\services;

use src\shared\domain\value_objects\Dinero;

/**
 * Explica las diferencias P/211 – G/11 que son solo de fechas: una mitad se
 * imputó a la fecha de cierre o antes (con la operación después) y la otra
 * se cuenta el día de la operación, fuera del período.
 *
 * Filas como las del listado de apuntes: iniciales, cantidad, fecha (operación)
 * y, si es distinta, fecha_imputacion.
 */
final class DesfaseImputacionVivienda
{
    /**
     * @param list<array<string, mixed>> $pDentro P/211 que cuentan hasta la fecha
     * @param list<array<string, mixed>> $gDentro G/11 que cuentan hasta la fecha
     * @param list<array<string, mixed>> $pDespues P/211 que cuentan después
     * @param list<array<string, mixed>> $gDespues G/11 que cuentan después
     * @return array<string, list<string>> iniciales (minúsculas) => notas
     */
    public static function notas(array $pDentro, array $gDentro, array $pDespues, array $gDespues, string $hasta): array
    {
        $notas = [];
        foreach ([['P/211', 'G/11', $pDentro, $gDespues], ['G/11', 'P/211', $gDentro, $pDespues]] as [$esta, $otra, $dentro, $despues]) {
            $usados = [];
            foreach ($dentro as $fila) {
                $imp = (string) ($fila['fecha_imputacion'] ?? '');
                $op = (string) ($fila['fecha'] ?? '');
                if ($imp === '' || $imp > $hasta || $op <= $hasta) {
                    continue;
                }
                $ini = strtolower(trim((string) ($fila['iniciales'] ?? '')));
                $cents = abs(Dinero::fromInput((string) ($fila['cantidad'] ?? '0'))->toCents());
                $importe = Dinero::fromCents($cents)->formatEs();
                $pareja = null;
                foreach ($despues as $i => $d) {
                    if (isset($usados[$i])
                        || strtolower(trim((string) ($d['iniciales'] ?? ''))) !== $ini
                        || abs(Dinero::fromInput((string) ($d['cantidad'] ?? '0'))->toCents()) !== $cents) {
                        continue;
                    }
                    $usados[$i] = true;
                    $pareja = $d;
                    break;
                }
                $notas[$ini][] = $pareja !== null
                    ? sprintf(
                        _('%s de %s € imputado al %s (operación %s); el %s del mismo importe se cuenta el %s. Es solo un desfase de fechas: al %s le falta la fecha de imputación %s.'),
                        $esta,
                        $importe,
                        self::es($imp),
                        self::es($op),
                        $otra,
                        self::es((string) ($pareja['fecha_imputacion'] ?? $pareja['fecha'] ?? '')),
                        $otra,
                        self::es($imp),
                    )
                    : sprintf(
                        _('%s de %s € imputado al %s (operación %s): el %s que lo acompaña tiene que llevar también la fecha de imputación %s.'),
                        $esta,
                        $importe,
                        self::es($imp),
                        self::es($op),
                        $otra,
                        self::es($imp),
                    );
            }
        }

        return $notas;
    }

    private static function es(string $ymd): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);

        return $d !== false ? $d->format('d/m/Y') : $ymd;
    }
}
