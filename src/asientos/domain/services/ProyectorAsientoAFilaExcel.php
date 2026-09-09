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
        if ($asiento->id === null) {
            throw new \InvalidArgumentException('El asiento debe estar persistido para proyectar');
        }

        $movs = $asiento->movimientos;
        $cuentasMov = [];
        foreach ($movs as $mov) {
            $cuenta = $cuentasPorId[$mov->cuentaId] ?? null;
            if ($cuenta !== null) {
                $cuentasMov[] = ['mov' => $mov, 'cuenta' => $cuenta];
            }
        }

        $origen = $this->deducirOrigen($asiento, $cuentasMov);
        $concepto = $this->movimientoConcepto($cuentasMov);
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
