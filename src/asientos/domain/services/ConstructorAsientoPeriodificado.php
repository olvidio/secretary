<?php

declare(strict_types=1);

namespace src\asientos\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

/**
 * Parte un asiento gasto/ingreso + tesorería en el par de periodificación (D13):
 * imputación (concepto contra puente) y tesorería (puente contra caja/banco).
 */
final class ConstructorAsientoPeriodificado
{
    /**
     * @return array{imputacion: Asiento, tesoreria: Asiento}
     */
    public static function partir(
        Asiento $origen,
        int $tesoreriaCuentaId,
        int $puenteCuentaId,
        DateTimeImmutable $fechaImputacion,
        DateTimeImmutable $fechaOperacion,
        int $ejercicioImputacionId,
        int $ejercicioOperacionId,
    ): array {
        if ($fechaImputacion->format('Y-m-d') === $fechaOperacion->format('Y-m-d')) {
            throw new InvalidArgumentException('La periodificación exige fechas de imputación y operación distintas');
        }
        if (count($origen->movimientos) !== 2) {
            throw new InvalidArgumentException('Solo se periodifica un asiento de dos movimientos (concepto y tesorería)');
        }
        if ($puenteCuentaId <= 0 || $puenteCuentaId === $tesoreriaCuentaId) {
            throw new InvalidArgumentException('Cuenta puente de periodificación no válida');
        }

        $conceptoMov = null;
        $tesoreriaMov = null;
        foreach ($origen->movimientos as $mov) {
            if ($mov->cuentaId === $tesoreriaCuentaId) {
                $tesoreriaMov = $mov;
            } else {
                $conceptoMov = $mov;
            }
        }
        if ($tesoreriaMov === null || $conceptoMov === null) {
            throw new InvalidArgumentException('El asiento a periodificar debe tener una línea de tesorería y otra de concepto');
        }
        if ($conceptoMov->cuentaId === $puenteCuentaId || $tesoreriaMov->cuentaId === $puenteCuentaId) {
            throw new InvalidArgumentException('El asiento origen no debe usar ya la cuenta de periodificación');
        }

        $imputacion = new Asiento(
            null,
            $ejercicioImputacionId,
            $origen->libro,
            null,
            $fechaImputacion,
            $origen->glosa,
            'normal',
            $origen->origen,
            $origen->personaId,
            [
                new Movimiento(null, 1, $conceptoMov->cuentaId, $conceptoMov->personaId, $conceptoMov->debeCents, $conceptoMov->haberCents),
                new Movimiento(null, 2, $puenteCuentaId, null, $tesoreriaMov->debeCents, $tesoreriaMov->haberCents),
            ],
            $origen->conceptoCodigo,
            null,
            $fechaOperacion,
        );

        $tesoreria = new Asiento(
            null,
            $ejercicioOperacionId,
            $origen->libro,
            null,
            $fechaOperacion,
            $origen->glosa,
            'periodificacion',
            $origen->origen,
            $origen->personaId,
            [
                new Movimiento(null, 1, $tesoreriaMov->cuentaId, $tesoreriaMov->personaId, $tesoreriaMov->debeCents, $tesoreriaMov->haberCents),
                new Movimiento(null, 2, $puenteCuentaId, null, $tesoreriaMov->haberCents, $tesoreriaMov->debeCents),
            ],
            $origen->conceptoCodigo,
            null,
            $fechaOperacion,
        );

        return ['imputacion' => $imputacion, 'tesoreria' => $tesoreria];
    }
}
