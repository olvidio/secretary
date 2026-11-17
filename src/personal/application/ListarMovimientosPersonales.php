<?php

declare(strict_types=1);

namespace src\personal\application;

use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Cuenta;
use src\apuntes\domain\contracts\PlantillaApunteRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\shared\domain\value_objects\Dinero;

final class ListarMovimientosPersonales
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly PlantillaApunteRepository $plantillas,
        private readonly EjercicioRepository $ejercicios,
    ) {
    }

    /**
     * Movimientos del ejercicio abierto entre dos fechas (el listado del mes).
     *
     * @return list<array<string, mixed>>
     */
    public function ejecutar(?string $desde, ?string $hasta): array
    {
        $ctx = $this->ambito->ejecutar();

        return $this->listar($ctx->centroId, $ctx->personaId, [$ctx->ejercicioId], $desde, $hasta);
    }

    /**
     * Movimientos entre dos fechas aunque caigan en ejercicios ya cerrados
     * (el buscador): se recorren todos los ejercicios del centro que solapan.
     *
     * @return list<array<string, mixed>>
     */
    public function ejecutarEntre(string $desde, string $hasta): array
    {
        $ctx = $this->ambito->ejecutar();
        $ids = [];
        foreach ($this->ejercicios->listarDeCentro($ctx->centroId) as $ej) {
            if ($ej->id !== null
                && $ej->fechaInicio->format('Y-m-d') <= $hasta
                && $ej->fechaFin->format('Y-m-d') >= $desde) {
                $ids[] = $ej->id;
            }
        }

        return $this->listar($ctx->centroId, $ctx->personaId, $ids, $desde, $hasta);
    }

    /**
     * @param list<int> $ejercicioIds
     * @return list<array<string, mixed>>
     */
    private function listar(int $centroId, int $personaId, array $ejercicioIds, ?string $desde, ?string $hasta): array
    {
        $filtros = ['libro' => 'X', 'persona_id' => $personaId];
        if ($desde !== null && $desde !== '') {
            $filtros['desde'] = $desde;
        }
        if ($hasta !== null && $hasta !== '') {
            $filtros['hasta'] = $hasta;
        }
        $nombres = [];
        $porCodigo = [];
        foreach ($this->cuentas->listarDePersona($centroId, $personaId, 'X') as $c) {
            if ($c->id !== null) {
                $nombres[$c->id] = $c;
                $porCodigo[$c->codigo] = $c;
            }
        }
        $codigoPorId = [];
        foreach ($this->cuentas->listarDeCentro($centroId) as $c) {
            if ($c->id !== null) {
                $codigoPorId[$c->id] = $c->codigo;
            }
        }
        $asientos = [];
        foreach ($ejercicioIds as $ejercicioId) {
            foreach ($this->asientos->listar($ejercicioId, $filtros) as $asiento) {
                if ($asiento->tipo === 'periodificacion') {
                    continue;
                }
                $asientos[] = $asiento;
            }
        }
        if (count($ejercicioIds) > 1) {
            usort($asientos, static fn (Asiento $a, Asiento $b): int => [$a->fecha, $a->id] <=> [$b->fecha, $b->id]);
        }
        $nombresPlantilla = $this->nombresPlantilla($centroId, $asientos);
        $out = [];
        foreach ($asientos as $asiento) {
            $out[] = $this->fila($asiento, $nombres, $porCodigo, $codigoPorId, $nombresPlantilla);
        }

        return $out;
    }

    /**
     * @param array<int, \src\ambito\domain\entity\Cuenta> $nombres
     * @param array<string, \src\ambito\domain\entity\Cuenta> $porCodigo
     * @param array<int, string> $codigoPorId
     * @param array<int, string> $nombresPlantilla
     * @return array<string, mixed>
     */
    private function fila(
        Asiento $asiento,
        array $nombres,
        array $porCodigo,
        array $codigoPorId,
        array $nombresPlantilla,
    ): array {
        $categoria = null;
        $tesoreria = null;
        $tesoreriaOrigen = null;
        $tesoreriaDestino = null;
        foreach ($asiento->movimientos as $mov) {
            $cuenta = $this->resolverCuenta($mov->cuentaId, $nombres, $porCodigo, $codigoPorId);
            if ($cuenta === null) {
                continue;
            }
            if (in_array($cuenta->tipo, ['ingreso', 'gasto'], true)) {
                $categoria = $cuenta;
            }
            if ($cuenta->tipo === 'tesoreria') {
                if ($asiento->tipo === 'traspaso') {
                    if ($mov->haberCents > 0) {
                        $tesoreriaOrigen = $cuenta;
                    }
                    if ($mov->debeCents > 0) {
                        $tesoreriaDestino = $cuenta;
                    }
                } else {
                    $tesoreria = $cuenta;
                }
            }
        }
        $cents = 0;
        foreach ($asiento->movimientos as $mov) {
            $cuenta = $this->resolverCuenta($mov->cuentaId, $nombres, $porCodigo, $codigoPorId);
            if ($cuenta !== null && $cuenta->tipo === 'tesoreria') {
                $cents = $mov->debeCents - $mov->haberCents;
                break;
            }
        }
        if ($cents === 0 && $categoria !== null) {
            foreach ($asiento->movimientos as $mov) {
                $cuenta = $this->resolverCuenta($mov->cuentaId, $nombres, $porCodigo, $codigoPorId);
                if ($cuenta !== null && $cuenta->id === $categoria->id) {
                    $cents = $mov->haberCents - $mov->debeCents;
                    break;
                }
            }
        }
        if ($asiento->tipo === 'traspaso') {
            $sentido = 'traspaso';
        } elseif ($cents === 0 && $categoria !== null) {
            $sentido = $categoria->tipo === 'gasto' ? 'gasto' : 'ingreso';
        } else {
            $sentido = $cents >= 0 ? 'ingreso' : 'gasto';
        }

        $plantillaId = $asiento->plantillaApunteId;
        $plantillaNombre = $plantillaId !== null ? ($nombresPlantilla[$plantillaId] ?? null) : null;
        $categoriaVisible = $plantillaNombre ?? $categoria?->nombre;

        return [
            'id' => $asiento->id,
            'fecha' => $asiento->fecha->format('Y-m-d'),
            'fecha_operacion' => $asiento->fechaOperacion()->format('Y-m-d'),
            'sentido' => $sentido,
            'cents' => abs($cents),
            'cantidad' => Dinero::fromCents(abs($cents))->toString(),
            'cantidad_es' => Dinero::fromCents(abs($cents))->formatEs(),
            'nota' => $asiento->glosa,
            'categoria_id' => $categoria?->id,
            'categoria' => $categoriaVisible,
            'categoria_codigo' => $categoria?->codigo,
            'plantilla_apunte_id' => $plantillaId,
            'plantilla_nombre' => $plantillaNombre,
            'tesoreria' => $tesoreria?->codigoMaestro,
            'tesoreria_origen' => $tesoreriaOrigen?->codigoMaestro,
            'tesoreria_destino' => $tesoreriaDestino?->codigoMaestro,
            'par_id' => $asiento->asientoParId,
            'gasto_generales' => $asiento->gastoGenerales,
            'concepto_generales' => $asiento->conceptoGenerales,
        ];
    }

    /**
     * Tras fusiones o restauraciones, un asiento puede referenciar cuentas de otra persona
     * con el mismo código; se resuelven contra el plan del titular del movimiento.
     *
     * @param array<int, \src\ambito\domain\entity\Cuenta> $nombres
     * @param array<string, \src\ambito\domain\entity\Cuenta> $porCodigo
     * @param array<int, string> $codigoPorId
     */
    private function resolverCuenta(int $cuentaId, array $nombres, array $porCodigo, array $codigoPorId): ?Cuenta
    {
        if (isset($nombres[$cuentaId])) {
            return $nombres[$cuentaId];
        }
        $codigo = $codigoPorId[$cuentaId] ?? null;
        if ($codigo === null) {
            return null;
        }

        return $porCodigo[$codigo] ?? null;
    }

    /**
     * @param list<Asiento> $asientos
     * @return array<int, string>
     */
    private function nombresPlantilla(int $centroId, array $asientos): array
    {
        $ids = [];
        foreach ($asientos as $asiento) {
            if ($asiento->plantillaApunteId !== null) {
                $ids[$asiento->plantillaApunteId] = true;
            }
        }
        $out = [];
        foreach (array_keys($ids) as $id) {
            $plantilla = $this->plantillas->porId($centroId, $id);
            if ($plantilla !== null) {
                $out[$id] = $plantilla->nombre;
            }
        }

        return $out;
    }
}
