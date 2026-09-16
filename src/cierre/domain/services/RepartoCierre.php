<?php

declare(strict_types=1);

namespace src\cierre\domain\services;

use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

final class RepartoCierre
{
    /**
     * Gastos G 201–215 menos las aportaciones G/11 (o G/14) ya hechas por quienes
     * no entran en el reparto del mes (no aporta, exento, iniciales desconocidas…).
     * Sirve para calcular la cuota objetivo de cada residente.
     *
     * @param array<string, Dinero> $aportacionesGenerales iniciales (minúsculas) => G/11 acumulado
     */
    public static function gastosNetosParaReparto(
        Dinero $gastosGenerales,
        array $personas,
        int $mes,
        array $aportacionesGenerales,
    ): Dinero {
        $enReparto = self::inicialesEnReparto($personas, $mes);
        $externos = Dinero::zero();
        foreach ($aportacionesGenerales as $ini => $importe) {
            if (!isset($enReparto[$ini])) {
                $externos = $externos->add($importe);
            }
        }
        $netos = $gastosGenerales->sub($externos);

        return $netos->isNegative() ? Dinero::zero() : $netos;
    }

    /**
     * Solo quienes aportan vivienda a generales. El intervalo de exención (llegada
     * o salida a mitad de año) deja fuera esos meses; quien no aporta no entra
     * aunque no tenga exención.
     *
     * Del total de gastos se resta todo lo ya pagado en G/11 (o G/14) ese mes,
     * incluido el exceso de quien haya aportado de más. Lo pendiente se reparte
     * entre residentes según su cuota (menos lo suyo ya pagado).
     *
     * Si se pasan aportaciones e objetivo del ejercicio, quien ya cubrió su
     * cuota acumulada no recibe cierre este mes; el hueco lo cubren quienes van
     * atrasados. El retraso de meses anteriores no se deshace.
     *
     * @param list<Persona> $personas
     * @param array<string, Dinero> $aportacionesGenerales iniciales => G/11 de este mes
     * @param array<string, Dinero> $aportacionesYtd iniciales => G/11 del ejercicio (sin el cierre de este mes)
     * @param array<string, Dinero> $objetivoYtd iniciales => suma de cuotas del ejercicio hasta este mes
     * @return list<array{persona:Persona,importe:Dinero,importe_bruto:Dinero,ya_imputado:Dinero}>
     */
    public static function calcular(
        Dinero $gastosGenerales,
        array $personas,
        int $mes,
        array $aportacionesGenerales = [],
        array $aportacionesYtd = [],
        array $objetivoYtd = [],
    ): array {
        $residentes = self::filtrarResidentes($personas, $mes);
        if ($residentes === []) {
            return [];
        }

        $restante = self::restantePorCubrir($gastosGenerales, $aportacionesGenerales);
        $baseObjetivo = self::gastosNetosParaReparto($gastosGenerales, $personas, $mes, $aportacionesGenerales);
        $cuota = $baseObjetivo->divInt(count($residentes));
        $usarEjercicio = $objetivoYtd !== [];

        $necesidades = [];
        $filas = [];
        foreach ($residentes as $p) {
            $bruto = $p->importeViviendaFijo ?? $cuota;
            $ini = strtolower(trim($p->iniciales));
            $previoMes = $aportacionesGenerales[$ini] ?? Dinero::zero();
            $previo = $usarEjercicio
                ? ($aportacionesYtd[$ini] ?? Dinero::zero())
                : $previoMes;
            if ($usarEjercicio) {
                $objetivo = $objetivoYtd[$ini] ?? $bruto;
                $necesidad = $objetivo->sub($previo);
            } else {
                $necesidad = $bruto->sub($previo);
            }
            if ($necesidad->isNegative()) {
                $necesidad = Dinero::zero();
            }
            $necesidades[] = $necesidad;
            $filas[] = [
                'persona' => $p,
                'importe_bruto' => $bruto,
                'ya_imputado' => $previo,
            ];
        }

        $importes = self::repartirRestante($restante, $necesidades);
        $out = [];
        foreach ($filas as $i => $fila) {
            $out[] = $fila + ['importe' => $importes[$i]];
        }

        return $out;
    }

    /**
     * Suma de cuotas (o importe fijo) de cada residente en los meses indicados.
     *
     * @param list<Persona> $personas
     * @param list<array{mes:int,gastos:Dinero,aportaciones:array<string,Dinero>}> $meses
     * @return array<string, Dinero>
     */
    public static function objetivoAcumulado(array $personas, array $meses): array
    {
        $out = [];
        foreach ($meses as $snap) {
            $residentes = self::filtrarResidentes($personas, $snap['mes']);
            if ($residentes === []) {
                continue;
            }
            $aportaciones = $snap['aportaciones'];
            $base = self::gastosNetosParaReparto($snap['gastos'], $personas, $snap['mes'], $aportaciones);
            $cuota = $base->divInt(count($residentes));
            foreach ($residentes as $p) {
                $ini = strtolower(trim($p->iniciales));
                $bruto = $p->importeViviendaFijo ?? $cuota;
                $out[$ini] = ($out[$ini] ?? Dinero::zero())->add($bruto);
            }
        }

        return $out;
    }

    /**
     * @param array<string, Dinero> $aportacionesGenerales
     */
    public static function restantePorCubrir(Dinero $gastosGenerales, array $aportacionesGenerales): Dinero
    {
        $pagado = Dinero::zero();
        foreach ($aportacionesGenerales as $importe) {
            $pagado = $pagado->add($importe);
        }
        $restante = $gastosGenerales->sub($pagado);

        return $restante->isNegative() ? Dinero::zero() : $restante;
    }

    /**
     * @param list<Dinero> $necesidades
     * @return list<Dinero>
     */
    private static function repartirRestante(Dinero $restante, array $necesidades): array
    {
        $n = count($necesidades);
        if ($n === 0 || $restante->isZero()) {
            return array_fill(0, $n, Dinero::zero());
        }

        $sumNec = Dinero::zero();
        foreach ($necesidades as $nec) {
            $sumNec = $sumNec->add($nec);
        }
        if ($sumNec->isZero()) {
            return self::repartirIgual($restante, $n);
        }

        if ($sumNec->compare($restante) <= 0) {
            return $necesidades;
        }

        $out = array_fill(0, $n, Dinero::zero());
        $asignado = Dinero::zero();
        $ultimoConNecesidad = null;
        foreach ($necesidades as $i => $nec) {
            if (!$nec->isZero()) {
                $ultimoConNecesidad = $i;
            }
        }
        if ($ultimoConNecesidad === null) {
            return $out;
        }

        foreach ($necesidades as $i => $nec) {
            if ($nec->isZero()) {
                continue;
            }
            if ($i === $ultimoConNecesidad) {
                $out[$i] = $restante->sub($asignado);
                break;
            }
            $parte = $restante->mulRatio((string) $nec->toCents(), (string) $sumNec->toCents());
            $out[$i] = $parte;
            $asignado = $asignado->add($parte);
        }

        return $out;
    }

    /**
     * @return list<Dinero>
     */
    private static function repartirIgual(Dinero $restante, int $n): array
    {
        if ($n <= 0) {
            return [];
        }
        $out = [];
        $asignado = Dinero::zero();
        $parte = $restante->divInt($n);
        for ($i = 0; $i < $n; ++$i) {
            if ($i === $n - 1) {
                $out[] = $restante->sub($asignado);
                break;
            }
            $out[] = $parte;
            $asignado = $asignado->add($parte);
        }

        return $out;
    }

    /**
     * @param list<Persona> $personas
     * @return array<string, true>
     */
    public static function inicialesEnReparto(array $personas, int $mes): array
    {
        $out = [];
        foreach (self::filtrarResidentes($personas, $mes) as $p) {
            $ini = strtolower(trim($p->iniciales));
            if ($ini !== '') {
                $out[$ini] = true;
            }
        }

        return $out;
    }

    /**
     * @param list<Persona> $personas
     * @return list<Persona>
     */
    private static function filtrarResidentes(array $personas, int $mes): array
    {
        $residentes = [];
        foreach ($personas as $p) {
            if ($p->exentaEnMes($mes)) {
                continue;
            }
            if (!$p->viviendaAportaGenerales) {
                continue;
            }
            $residentes[] = $p;
        }

        return $residentes;
    }
}
