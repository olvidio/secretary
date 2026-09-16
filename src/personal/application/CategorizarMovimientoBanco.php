<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\personal\domain\services\ConstructorAsientoPersonal;
use src\personal\domain\services\ResolverCategoriaPlantillaPersonal;

final class CategorizarMovimientoBanco
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly ResolverCategoriaPlantillaPersonal $categoriaPlantilla,
    ) {
    }

    public function ejecutar(int $asientoId, int $cuentaId, ?string $observaciones = null): Asiento
    {
        $ctx = $this->ambito->ejecutar();
        $contexto = $this->contextoAsiento($asientoId, $ctx->personaId);
        $nueva = $contexto['nombres'][$cuentaId] ?? null;
        if ($nueva === null || $nueva->id === null || !in_array($nueva->tipo, ['ingreso', 'gasto'], true)) {
            throw new InvalidArgumentException('Categoría no válida');
        }
        if (AsegurarPlanPersonal::esPendiente($nueva->codigo)) {
            throw new InvalidArgumentException('Elija una categoría del plan');
        }
        $sentido = $contexto['categoriaActual']?->tipo ?? ($nueva->tipo);
        if ($nueva->tipo !== $sentido) {
            throw new InvalidArgumentException(
                'Esa categoría es de ' . $nueva->tipo . '; el movimiento es un ' . $sentido
            );
        }
        $glosa = $this->glosa($contexto['asiento'], $observaciones);
        $reconstruido = ConstructorAsientoPersonal::movimiento(
            $contexto['asiento']->ejercicioId,
            $ctx->personaId,
            $contexto['asiento']->fecha,
            $glosa,
            $sentido,
            $nueva->id,
            $contexto['tesoreria']->id,
            $contexto['cents'],
            $nueva->codigo,
            $contexto['asiento']->fechaOperacion(),
            $contexto['asiento']->origen,
        );

        return $this->guardar($contexto['asiento'], $reconstruido, $glosa, $nueva->codigo);
    }

    public function conPlantilla(int $asientoId, int $plantillaId, ?string $observaciones = null): Asiento
    {
        $ctx = $this->ambito->ejecutar();
        $contexto = $this->contextoAsiento($asientoId, $ctx->personaId);
        $sentido = $contexto['categoriaActual']?->tipo;
        if ($sentido !== 'gasto') {
            throw new InvalidArgumentException('Las plantillas del centro solo aplican a gastos del banco');
        }
        $nueva = $this->categoriaPlantilla->ejecutar($ctx->centroId, $ctx->personaId, $plantillaId);
        $glosa = $this->glosa($contexto['asiento'], $observaciones);
        $reconstruido = ConstructorAsientoPersonal::movimiento(
            $contexto['asiento']->ejercicioId,
            $ctx->personaId,
            $contexto['asiento']->fecha,
            $glosa,
            'gasto',
            $nueva->id,
            $contexto['tesoreria']->id,
            $contexto['cents'],
            $nueva->codigo,
            $contexto['asiento']->fechaOperacion(),
            $contexto['asiento']->origen,
        );

        return $this->guardar($contexto['asiento'], $reconstruido, $glosa, $nueva->codigo, $plantillaId);
    }

    public function traspasoACaja(int $asientoId, ?string $observaciones = null): Asiento
    {
        $ctx = $this->ambito->ejecutar();
        $contexto = $this->contextoAsiento($asientoId, $ctx->personaId);
        $caja = $this->tesoreria($contexto['nombres'], 'CAJA');
        $banco = $this->tesoreria($contexto['nombres'], 'BANCO');
        if ($contexto['tesoreria']->codigoMaestro !== 'BANCO') {
            throw new InvalidArgumentException('Solo se puede traspasar un movimiento del banco');
        }
        $sentido = $contexto['categoriaActual']?->tipo;
        if ($sentido === 'gasto') {
            [$origenId, $destinoId] = [$banco->id, $caja->id];
        } elseif ($sentido === 'ingreso') {
            [$origenId, $destinoId] = [$caja->id, $banco->id];
        } else {
            throw new InvalidArgumentException('No se puede traspasar este movimiento');
        }
        $glosa = $this->glosa($contexto['asiento'], $observaciones);
        $reconstruido = ConstructorAsientoPersonal::traspaso(
            $contexto['asiento']->ejercicioId,
            $ctx->personaId,
            $contexto['asiento']->fecha,
            $glosa,
            $origenId,
            $destinoId,
            $contexto['cents'],
            $contexto['asiento']->origen,
            $contexto['asiento']->fechaOperacion(),
        );

        return $this->guardar($contexto['asiento'], $reconstruido, $glosa, null);
    }

    /**
     * @return array{
     *     asiento: Asiento,
     *     nombres: array<int, Cuenta>,
     *     tesoreria: Cuenta,
     *     categoriaActual: ?Cuenta,
     *     cents: int
     * }
     */
    private function contextoAsiento(int $asientoId, int $personaId): array
    {
        $ctx = $this->ambito->ejecutar();
        $asiento = $this->asientos->porId($asientoId);
        if (
            $asiento === null
            || $asiento->id === null
            || $asiento->libro !== 'X'
            || $asiento->personaId !== $personaId
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

        return [
            'asiento' => $asiento,
            'nombres' => $nombres,
            'tesoreria' => $tesoreria,
            'categoriaActual' => $categoriaActual,
            'cents' => $cents,
        ];
    }

    /** @param array<int, Cuenta> $nombres */
    private function tesoreria(array $nombres, string $maestro): Cuenta
    {
        foreach ($nombres as $cuenta) {
            if ($cuenta->tipo === 'tesoreria' && $cuenta->codigoMaestro === $maestro && $cuenta->id !== null) {
                return $cuenta;
            }
        }

        throw new InvalidArgumentException('No hay cuenta de tesorería ' . $maestro);
    }

    private function glosa(Asiento $asiento, ?string $observaciones): ?string
    {
        if ($observaciones === null) {
            return $asiento->glosa;
        }
        $obs = trim($observaciones);
        if (mb_strlen($obs) > 250) {
            $obs = mb_substr($obs, 0, 250);
        }

        return $obs === '' ? null : $obs;
    }

    private function guardar(
        Asiento $asiento,
        Asiento $reconstruido,
        ?string $glosa,
        ?string $conceptoCodigo,
        ?int $plantillaApunteId = null,
    ): Asiento {
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
            $conceptoCodigo,
            $asiento->asientoParId,
            $asiento->fechaOperacion(),
            $asiento->remesaId,
            $asiento->gastoGenerales,
            $asiento->conceptoGenerales,
            $plantillaApunteId,
        ));
    }
}
