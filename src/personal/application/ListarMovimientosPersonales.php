<?php

declare(strict_types=1);

namespace src\personal\application;

use src\ambito\domain\contracts\CuentaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\shared\domain\value_objects\Dinero;

final class ListarMovimientosPersonales
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ejecutar(?string $desde, ?string $hasta): array
    {
        $ctx = $this->ambito->ejecutar();
        $filtros = ['libro' => 'X', 'persona_id' => $ctx->personaId];
        if ($desde !== null && $desde !== '') {
            $filtros['desde'] = $desde;
        }
        if ($hasta !== null && $hasta !== '') {
            $filtros['hasta'] = $hasta;
        }
        $nombres = [];
        foreach ($this->cuentas->listarDePersona($ctx->centroId, $ctx->personaId, 'X') as $c) {
            if ($c->id !== null) {
                $nombres[$c->id] = $c;
            }
        }
        $out = [];
        foreach ($this->asientos->listar($ctx->ejercicioId, $filtros) as $asiento) {
            if ($asiento->tipo === 'periodificacion') {
                continue;
            }
            $out[] = $this->fila($asiento, $nombres);
        }

        return $out;
    }

    /**
     * @param array<int, \src\ambito\domain\entity\Cuenta> $nombres
     * @return array<string, mixed>
     */
    private function fila(Asiento $asiento, array $nombres): array
    {
        $categoria = null;
        $tesoreria = null;
        foreach ($asiento->movimientos as $mov) {
            $cuenta = $nombres[$mov->cuentaId] ?? null;
            if ($cuenta === null) {
                continue;
            }
            if (in_array($cuenta->tipo, ['ingreso', 'gasto'], true)) {
                $categoria = $cuenta;
            }
            if ($cuenta->tipo === 'tesoreria') {
                $tesoreria = $cuenta;
            }
        }
        $cents = 0;
        foreach ($asiento->movimientos as $mov) {
            $cuenta = $nombres[$mov->cuentaId] ?? null;
            if ($cuenta !== null && $cuenta->tipo === 'tesoreria') {
                $cents = $mov->debeCents - $mov->haberCents;
                break;
            }
        }
        if ($cents === 0 && $categoria !== null) {
            foreach ($asiento->movimientos as $mov) {
                if ($mov->cuentaId === $categoria->id) {
                    $cents = $mov->haberCents - $mov->debeCents;
                    break;
                }
            }
        }
        $sentido = $asiento->tipo === 'traspaso' ? 'traspaso' : ($cents >= 0 ? 'ingreso' : 'gasto');

        return [
            'id' => $asiento->id,
            'fecha' => $asiento->fecha->format('Y-m-d'),
            'fecha_operacion' => $asiento->fechaOperacion()->format('Y-m-d'),
            'sentido' => $sentido,
            'cantidad' => Dinero::fromCents(abs($cents))->toString(),
            'cantidad_es' => Dinero::fromCents(abs($cents))->formatEs(),
            'nota' => $asiento->glosa,
            'categoria_id' => $categoria?->id,
            'categoria' => $categoria?->nombre,
            'categoria_codigo' => $categoria?->codigo,
            'tesoreria' => $tesoreria?->codigoMaestro,
            'par_id' => $asiento->asientoParId,
        ];
    }
}
