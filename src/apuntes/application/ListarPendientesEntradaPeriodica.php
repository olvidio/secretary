<?php

declare(strict_types=1);

namespace src\apuntes\application;

use DateTimeImmutable;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\EntradaPeriodicaRepository;
use src\apuntes\domain\contracts\PlantillaApunteRepository;
use src\apuntes\domain\services\CalculadorVencimientosEntradaPeriodica;
use src\apuntes\domain\value_objects\ReferenciaPlantillaEnConcepto;
use src\conceptos\application\ResolverConceptosCentro;

final class ListarPendientesEntradaPeriodica
{
    public function __construct(
        private readonly EntradaPeriodicaRepository $entradas,
        private readonly ResolverAmbitoActual $ambito,
        private readonly ComprobarAccesoCentroSg $centroSg,
        private readonly CalculadorVencimientosEntradaPeriodica $calculador,
        private readonly ResolverConceptosCentro $conceptos,
        private readonly PlantillaApunteRepository $plantillas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(?string $hastaYmd = null): array
    {
        $this->centroSg->ejecutar();
        $ctx = $this->ambito->ejecutar();
        $hasta = $hastaYmd !== null && $hastaYmd !== ''
            ? DateTimeImmutable::createFromFormat('Y-m-d', $hastaYmd)
            : new DateTimeImmutable('today');
        if ($hasta === false) {
            $hasta = new DateTimeImmutable('today');
        }

        $out = [];
        foreach ($this->entradas->listar($ctx->centroId) as $def) {
            if (!$def->activa) {
                continue;
            }
            $ejecutadas = $this->entradas->fechasEjecutadas($ctx->centroId, (int) $def->id);
            $etiquetaConcepto = $this->etiquetaConcepto($ctx->centroId, $def->conceptoCodigo);
            foreach ($this->calculador->pendientes($def, $hasta, $ejecutadas) as $fecha) {
                $out[] = [
                    'entrada_id' => $def->id,
                    'fecha' => $fecha->format('Y-m-d'),
                    'iniciales' => $def->iniciales,
                    'concepto_codigo' => $def->conceptoCodigo,
                    'concepto_etiqueta' => $etiquetaConcepto,
                    'observaciones' => $def->observaciones,
                    'cantidad' => $def->cantidad->toString(),
                    'periodicidad' => $def->periodicidad->valor,
                    'periodicidad_etiqueta' => $def->periodicidad->etiqueta(),
                ];
            }
        }
        usort($out, static function (array $a, array $b): int {
            $c = strcmp($a['fecha'], $b['fecha']);
            if ($c !== 0) {
                return $c;
            }

            return ($a['entrada_id'] ?? 0) <=> ($b['entrada_id'] ?? 0);
        });

        return $out;
    }

    private function etiquetaConcepto(int $centroId, string $codigo): string
    {
        $plantillaId = ReferenciaPlantillaEnConcepto::idDesdeCodigo($codigo);
        if ($plantillaId !== null) {
            $plantilla = $this->plantillas->porId($centroId, $plantillaId);

            return $plantilla !== null ? $plantilla->nombre : $codigo;
        }
        $concepto = $this->conceptos->buscar($centroId, 'G', $codigo);

        return $concepto?->etiqueta ?? $codigo;
    }
}
