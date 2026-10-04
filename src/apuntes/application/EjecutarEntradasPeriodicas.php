<?php

declare(strict_types=1);

namespace src\apuntes\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\EntradaPeriodicaRepository;
use src\apuntes\domain\contracts\PlantillaApunteRepository;
use src\apuntes\domain\services\CalculadorVencimientosEntradaPeriodica;
use src\apuntes\domain\value_objects\ReferenciaPlantillaEnConcepto;

final class EjecutarEntradasPeriodicas
{
    public function __construct(
        private readonly EntradaPeriodicaRepository $entradas,
        private readonly ResolverAmbitoActual $ambito,
        private readonly ComprobarAccesoCentroSg $centroSg,
        private readonly CalculadorVencimientosEntradaPeriodica $calculador,
        private readonly CrearApuntesDeEntrada $crearApunte,
        private readonly PlantillaApunteRepository $plantillas,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{ejecutados: int, apuntes: list<array<string, mixed>>}
     */
    public function ejecutar(array $datos): array
    {
        $this->centroSg->ejecutar();
        $ctx = $this->ambito->ejecutar();
        $lineas = $datos['lineas'] ?? null;
        if (!is_array($lineas) || $lineas === []) {
            throw new InvalidArgumentException(_("Seleccione al menos un movimiento"));
        }
        $hasta = new DateTimeImmutable('today');
        $apuntesOut = [];
        $ejecutados = 0;

        foreach ($lineas as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $id = (int) ($raw['entrada_id'] ?? $raw['id'] ?? 0);
            $fechaRaw = trim((string) ($raw['fecha'] ?? ''));
            if ($id <= 0 || $fechaRaw === '') {
                throw new InvalidArgumentException(_("Línea de ejecución incompleta"));
            }
            $def = $this->entradas->porId($ctx->centroId, $id);
            if ($def === null) {
                throw new InvalidArgumentException(_("Entrada periódica no encontrada"));
            }
            $fecha = DateTimeImmutable::createFromFormat('Y-m-d', $fechaRaw);
            if ($fecha === false) {
                throw new InvalidArgumentException(_("Fecha no válida"));
            }
            $ejecutadas = $this->entradas->fechasEjecutadas($ctx->centroId, $id);
            $pendientes = $this->calculador->pendientes($def, $hasta, $ejecutadas);
            $valida = false;
            foreach ($pendientes as $p) {
                if ($p->format('Y-m-d') === $fechaRaw) {
                    $valida = true;
                    break;
                }
            }
            if (!$valida) {
                throw new InvalidArgumentException(
                    sprintf(_("La fecha %s no está pendiente para esta entrada"), $fechaRaw),
                );
            }

            $plantillaId = ReferenciaPlantillaEnConcepto::idDesdeCodigo($def->conceptoCodigo);
            if ($plantillaId !== null) {
                $plantilla = $this->plantillas->porId($ctx->centroId, $plantillaId);
                if ($plantilla === null || strtoupper($plantilla->cuenta) !== 'G') {
                    throw new InvalidArgumentException(_("Plantilla no válida"));
                }
                if ($plantilla->lineas === []) {
                    throw new InvalidArgumentException(_("La plantilla no tiene movimientos"));
                }
                foreach ($plantilla->lineas as $i => $linea) {
                    $obsDef = $def->observaciones ?? '';
                    $obs = $i === 0 && $obsDef !== ''
                        ? $obsDef
                        : ($linea->observaciones ?? '');
                    $creados = $this->crearApunte->ejecutar([
                        'fecha' => $fechaRaw,
                        'cuenta' => $linea->cuenta,
                        'origen' => $linea->origen,
                        'iniciales' => $def->iniciales,
                        'concepto_codigo' => $linea->conceptoCodigo,
                        'observaciones' => $obs,
                        'cantidad' => $def->cantidad->toString(),
                    ]);
                    foreach ($creados as $fila) {
                        $apuntesOut[] = $fila->toArray();
                    }
                }
            } else {
                $creados = $this->crearApunte->ejecutar([
                    'fecha' => $fechaRaw,
                    'cuenta' => 'G',
                    'origen' => 'C',
                    'iniciales' => $def->iniciales,
                    'concepto_codigo' => $def->conceptoCodigo,
                    'observaciones' => $def->observaciones ?? '',
                    'cantidad' => $def->cantidad->toString(),
                ]);
                foreach ($creados as $fila) {
                    $apuntesOut[] = $fila->toArray();
                }
            }
            $this->entradas->registrarEjecucion($id, $fechaRaw);
            ++$ejecutados;
        }

        return ['ejecutados' => $ejecutados, 'apuntes' => $apuntesOut];
    }
}
