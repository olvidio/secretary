<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\personal\domain\services\ConstructorAsientoPersonal;

final class CategorizarMovimientoBanco
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    public function ejecutar(int $asientoId, int $cuentaId, ?string $observaciones = null): Asiento
    {
        $ctx = $this->ambito->ejecutar();
        $asiento = $this->asientos->porId($asientoId);
        if (
            $asiento === null
            || $asiento->id === null
            || $asiento->libro !== 'X'
            || $asiento->personaId !== $ctx->personaId
            || $asiento->origen !== 'banco'
        ) {
            throw new InvalidArgumentException('Movimiento de banco no encontrado');
        }
        $nombres = [];
        foreach ($this->cuentas->listarDePersona($ctx->centroId, $ctx->personaId, 'X') as $c) {
            if ($c->id !== null) {
                $nombres[$c->id] = $c;
            }
        }
        $tesoreria = null;
        $categoriaActual = null;
        $cents = 0;
        foreach ($asiento->movimientos as $mov) {
            $cuenta = $nombres[$mov->cuentaId] ?? null;
            if ($cuenta === null) {
                continue;
            }
            if ($cuenta->tipo === 'tesoreria') {
                $tesoreria = $cuenta;
                $cents = abs($mov->debeCents - $mov->haberCents);
            }
            if (in_array($cuenta->tipo, ['ingreso', 'gasto'], true)) {
                $categoriaActual = $cuenta;
            }
        }
        if ($tesoreria === null || $tesoreria->id === null || $cents <= 0) {
            throw new InvalidArgumentException('El movimiento no tiene tesorería');
        }
        $nueva = $nombres[$cuentaId] ?? null;
        if ($nueva === null || $nueva->id === null || !in_array($nueva->tipo, ['ingreso', 'gasto'], true)) {
            throw new InvalidArgumentException('Categoría no válida');
        }
        if (AsegurarPlanPersonal::esPendiente($nueva->codigo)) {
            throw new InvalidArgumentException('Elija una categoría del plan');
        }
        $sentido = $categoriaActual?->tipo ?? ($nueva->tipo);
        if ($nueva->tipo !== $sentido) {
            throw new InvalidArgumentException(
                'Esa categoría es de ' . $nueva->tipo . '; el movimiento es un ' . $sentido
            );
        }
        $glosa = $asiento->glosa;
        if ($observaciones !== null) {
            $obs = trim($observaciones);
            if (mb_strlen($obs) > 250) {
                $obs = mb_substr($obs, 0, 250);
            }
            $glosa = $obs === '' ? null : $obs;
        }
        $reconstruido = ConstructorAsientoPersonal::movimiento(
            $asiento->ejercicioId,
            $ctx->personaId,
            $asiento->fecha,
            $glosa,
            $sentido,
            $nueva->id,
            $tesoreria->id,
            $cents,
            $nueva->codigo,
            $asiento->fechaOperacion(),
            $asiento->origen,
        );

        return $this->asientos->actualizar(new Asiento(
            $asiento->id,
            $asiento->ejercicioId,
            $asiento->libro,
            $asiento->numero,
            $asiento->fecha,
            $glosa,
            $reconstruido->tipo,
            $asiento->origen,
            $asiento->personaId,
            $reconstruido->movimientos,
            $nueva->codigo,
            $asiento->asientoParId,
            $asiento->fechaOperacion(),
            $asiento->remesaId,
        ));
    }
}
