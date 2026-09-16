<?php

declare(strict_types=1);

namespace src\asientos\domain\services;

use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\value_objects\FilaApunteExcel;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

/** Proyección pura de un asiento al shape Excel (`Apunte::toArray()`). */
final class ProyectorAsientoAFilaExcel
{
    /**
     * @param array<int, Cuenta> $cuentasPorId
     * @param array<int, Persona> $personasPorId
     */
    public function proyectar(
        Asiento $asiento,
        array $cuentasPorId,
        array $personasPorId,
    ): FilaApunteExcel {
        $filas = $this->proyectarFilas($asiento, $cuentasPorId, $personasPorId);
        if ($filas === []) {
            throw new \InvalidArgumentException('El asiento no tiene líneas proyectables a apunte');
        }

        return $filas[0];
    }

    /**
     * @param array<int, Cuenta> $cuentasPorId
     * @param array<int, Persona> $personasPorId
     * @return list<FilaApunteExcel>
     */
    public function proyectarFilas(
        Asiento $asiento,
        array $cuentasPorId,
        array $personasPorId,
    ): array {
        if ($asiento->id === null) {
            throw new \InvalidArgumentException('El asiento debe estar persistido para proyectar');
        }

        $cuentasMov = $this->cuentasDe($asiento, $cuentasPorId);
        if ($asiento->tipo === 'asignacion_cc') {
            return [];
        }
        if ($asiento->tipo === 'remesa' || $asiento->origen === 'remesa') {
            return $this->proyectarFilasRemesa($asiento, $cuentasMov, $personasPorId);
        }
        if ($asiento->origen === 'asignacion') {
            return $this->proyectarFilasAsignacion($asiento, $cuentasMov, $personasPorId);
        }

        return [$this->proyectarFila($asiento, $cuentasMov, $personasPorId, null)];
    }

    /**
     * @param list<array{mov: Movimiento, cuenta: Cuenta}> $cuentasMov
     * @return list<FilaApunteExcel>
     */
    private function proyectarFilasAsignacion(
        Asiento $asiento,
        array $cuentasMov,
        array $personasPorId,
    ): array {
        $filas = [];
        $ingreso111 = null;
        $total111 = 0;
        foreach ($cuentasMov as $item) {
            $tipo = $item['cuenta']->tipo;
            if ($tipo === 'gasto') {
                $filas[] = $this->proyectarFila($asiento, $cuentasMov, $personasPorId, $item);
            } elseif ($tipo === 'ingreso') {
                $ingreso111 = $item;
                $total111 += $item['mov']->haberCents - $item['mov']->debeCents;
            }
        }
        if ($ingreso111 !== null && $total111 > 0) {
            $filas[] = $this->proyectarFilaConCantidad(
                $asiento,
                $cuentasMov,
                $personasPorId,
                $ingreso111,
                $total111,
            );
        }

        return $filas;
    }

    /**
     * @param list<array{mov: Movimiento, cuenta: Cuenta}> $cuentasMov
     * @return list<FilaApunteExcel>
     */
    private function proyectarFilasRemesa(
        Asiento $asiento,
        array $cuentasMov,
        array $personasPorId,
    ): array {
        /** @var list<array{item: array{mov: Movimiento, cuenta: Cuenta}, cents: int, codigo: string}> $gastos */
        $gastos = [];
        /** @var list<array{item: array{mov: Movimiento, cuenta: Cuenta}, cents: int, codigo: string}> $ingresos */
        $ingresos = [];
        $totalGastos = 0;
        $totalIngresos = 0;
        foreach ($cuentasMov as $item) {
            $tipo = $item['cuenta']->tipo;
            if ($tipo === 'gasto') {
                $cents = $item['mov']->debeCents - $item['mov']->haberCents;
                if ($cents === 0) {
                    continue;
                }
                $gastos[] = ['item' => $item, 'cents' => $cents, 'codigo' => $item['cuenta']->codigoMaestro];
                $totalGastos += $cents;
            } elseif (in_array($tipo, ['ingreso', 'patrimonio'], true)) {
                $cents = $item['mov']->haberCents - $item['mov']->debeCents;
                if ($cents === 0) {
                    continue;
                }
                $ingresos[] = ['item' => $item, 'cents' => $cents, 'codigo' => $item['cuenta']->codigoMaestro];
                $totalIngresos += $cents;
            }
        }

        if ($gastos === [] && $ingresos === []) {
            return [];
        }

        $filas = [];
        foreach ($gastos as $gasto) {
            $filas[] = $this->proyectarFila($asiento, $cuentasMov, $personasPorId, $gasto['item']);
        }

        $exceso = $totalIngresos - $totalGastos;
        if ($exceso <= 0) {
            foreach ($ingresos as $ingreso) {
                $filas[] = $this->proyectarFila($asiento, $cuentasMov, $personasPorId, $ingreso['item']);
            }

            return $filas;
        }

        usort($ingresos, static function (array $a, array $b): int {
            if ($a['codigo'] === '111' && $b['codigo'] !== '111') {
                return -1;
            }
            if ($b['codigo'] === '111' && $a['codigo'] !== '111') {
                return 1;
            }

            return $a['codigo'] <=> $b['codigo'];
        });

        $restante = $totalGastos;
        foreach ($ingresos as $ingreso) {
            if ($restante <= 0) {
                break;
            }
            $mostrar = min($ingreso['cents'], $restante);
            if ($mostrar <= 0) {
                continue;
            }
            $restante -= $mostrar;
            $filas[] = $this->proyectarFilaConCantidad(
                $asiento,
                $cuentasMov,
                $personasPorId,
                $ingreso['item'],
                $mostrar,
            );
        }

        $filas[] = $this->proyectarSaldoCcRemesa($asiento, $cuentasMov, $personasPorId, $exceso);

        return $filas;
    }

    /**
     * @param list<array{mov: Movimiento, cuenta: Cuenta}> $cuentasMov
     * @param array{mov: Movimiento, cuenta: Cuenta} $concepto
     */
    private function proyectarFilaConCantidad(
        Asiento $asiento,
        array $cuentasMov,
        array $personasPorId,
        array $concepto,
        int $cents,
    ): FilaApunteExcel {
        $origen = $this->deducirOrigen($asiento, $cuentasMov);
        $iniciales = null;
        if ($asiento->personaId !== null && isset($personasPorId[$asiento->personaId])) {
            $iniciales = $personasPorId[$asiento->personaId]->iniciales;
        }

        return new FilaApunteExcel(
            $asiento->id,
            $asiento->fechaOperacion(),
            $asiento->libro,
            $origen,
            $iniciales,
            $concepto['cuenta']->codigo,
            $asiento->glosa,
            Dinero::fromCents($cents),
            false,
            $asiento->fecha->format('Y-m-d') !== $asiento->fechaOperacion()->format('Y-m-d')
                ? $asiento->fecha
                : null,
        );
    }

    /**
     * @param list<array{mov: Movimiento, cuenta: Cuenta}> $cuentasMov
     */
    private function proyectarSaldoCcRemesa(
        Asiento $asiento,
        array $cuentasMov,
        array $personasPorId,
        int $sobranteCents,
    ): FilaApunteExcel {
        $origen = $this->deducirOrigen($asiento, $cuentasMov);
        $iniciales = null;
        if ($asiento->personaId !== null && isset($personasPorId[$asiento->personaId])) {
            $iniciales = $personasPorId[$asiento->personaId]->iniciales;
        }

        return new FilaApunteExcel(
            $asiento->id,
            $asiento->fechaOperacion(),
            $asiento->libro,
            $origen,
            $iniciales,
            '9',
            $asiento->glosa,
            Dinero::fromCents($sobranteCents),
            false,
            $asiento->fecha->format('Y-m-d') !== $asiento->fechaOperacion()->format('Y-m-d')
                ? $asiento->fecha
                : null,
        );
    }

    /**
     * @param list<array{mov: Movimiento, cuenta: Cuenta}> $cuentasMov
     * @param array{mov: Movimiento, cuenta: Cuenta}|null $conceptoForzado
     */
    private function proyectarFila(
        Asiento $asiento,
        array $cuentasMov,
        array $personasPorId,
        ?array $conceptoForzado,
    ): FilaApunteExcel {
        $origen = $this->deducirOrigen($asiento, $cuentasMov);
        $concepto = $conceptoForzado ?? $this->movimientoConcepto($cuentasMov);
        $conceptoCodigo = $asiento->conceptoCodigo
            ?? ($concepto !== null ? $concepto['cuenta']->codigo : '');

        $cantidad = $this->calcularCantidad($asiento->tipo, $concepto, $cuentasMov);

        $iniciales = null;
        if ($asiento->personaId !== null && isset($personasPorId[$asiento->personaId])) {
            $iniciales = $personasPorId[$asiento->personaId]->iniciales;
        }

        return new FilaApunteExcel(
            $asiento->id,
            $asiento->fechaOperacion(),
            $asiento->libro,
            $origen,
            $iniciales,
            $conceptoCodigo,
            $asiento->glosa,
            $cantidad,
            $asiento->tipo === 'cierre',
            $asiento->fecha->format('Y-m-d') !== $asiento->fechaOperacion()->format('Y-m-d')
                ? $asiento->fecha
                : null,
        );
    }

    /**
     * Una fila Excel para el par imputación + tesorería (D13).
     *
     * @param array<int, Cuenta> $cuentasPorId
     * @param array<int, Persona> $personasPorId
     */
    public function proyectarPar(
        Asiento $imputacion,
        Asiento $tesoreria,
        array $cuentasPorId,
        array $personasPorId,
    ): FilaApunteExcel {
        $filaConcepto = $this->proyectar($imputacion, $cuentasPorId, $personasPorId);
        $origen = $this->deducirOrigen($tesoreria, $this->cuentasDe($tesoreria, $cuentasPorId));
        $id = $imputacion->id ?? $tesoreria->id;
        if ($id === null) {
            throw new \InvalidArgumentException('El asiento debe estar persistido para proyectar');
        }

        return new FilaApunteExcel(
            $id,
            $tesoreria->fechaOperacion(),
            $filaConcepto->cuenta,
            $origen,
            $filaConcepto->iniciales,
            $filaConcepto->conceptoCodigo,
            $filaConcepto->observaciones,
            $filaConcepto->cantidad,
            false,
            $imputacion->fecha,
        );
    }

    /**
     * @param array<int, Cuenta> $cuentasPorId
     * @return list<array{mov: Movimiento, cuenta: Cuenta}>
     */
    private function cuentasDe(Asiento $asiento, array $cuentasPorId): array
    {
        $cuentasMov = [];
        foreach ($asiento->movimientos as $mov) {
            $cuenta = $cuentasPorId[$mov->cuentaId] ?? null;
            if ($cuenta !== null) {
                $cuentasMov[] = ['mov' => $mov, 'cuenta' => $cuenta];
            }
        }

        return $cuentasMov;
    }

    /**
     * @param list<array{mov: Movimiento, cuenta: Cuenta}> $cuentasMov
     */
    private function deducirOrigen(Asiento $asiento, array $cuentasMov): string
    {
        if ($asiento->tipo === 'traspaso') {
            return $asiento->conceptoCodigo === '42' ? 'B' : 'C';
        }

        $tieneCaja = false;
        $tieneBanco = false;
        $tienePersonal = false;

        foreach ($cuentasMov as $item) {
            $cuenta = $item['cuenta'];
            if ($cuenta->tipo === 'tesoreria') {
                if ($cuenta->codigoMaestro === 'CAJA') {
                    $tieneCaja = true;
                }
                if ($cuenta->codigoMaestro === 'BANCO') {
                    $tieneBanco = true;
                }
            }
            if ($cuenta->tipo === 'personal' || $cuenta->codigo === 'DEUDORES.VIV') {
                $tienePersonal = true;
            }
        }

        if ($tieneCaja && !$tieneBanco) {
            return 'C';
        }
        if ($tieneBanco && !$tieneCaja) {
            return 'B';
        }
        if ($tienePersonal) {
            return 'A';
        }

        return 'A';
    }

    /**
     * @param list<array{mov: Movimiento, cuenta: Cuenta}> $cuentasMov
     * @return array{mov: Movimiento, cuenta: Cuenta}|null
     */
    private function movimientoConcepto(array $cuentasMov): ?array
    {
        foreach ($cuentasMov as $item) {
            $tipo = $item['cuenta']->tipo;
            if (in_array($tipo, ['ingreso', 'gasto', 'patrimonio'], true)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param list<array{mov: Movimiento, cuenta: Cuenta}> $cuentasMov
     */
    private function calcularCantidad(string $tipoAsiento, ?array $concepto, array $cuentasMov): Dinero
    {
        if ($tipoAsiento === 'traspaso') {
            foreach ($cuentasMov as $item) {
                if ($item['cuenta']->tipo === 'tesoreria') {
                    $cents = max($item['mov']->debeCents, $item['mov']->haberCents);

                    return Dinero::fromCents($cents);
                }
            }

            return Dinero::zero();
        }

        if ($concepto === null) {
            return Dinero::zero();
        }

        $mov = $concepto['mov'];
        $tipo = $concepto['cuenta']->tipo;

        if ($tipo === 'ingreso' || $tipo === 'patrimonio') {
            return Dinero::fromCents($mov->haberCents - $mov->debeCents);
        }

        return Dinero::fromCents($mov->debeCents - $mov->haberCents);
    }
}
