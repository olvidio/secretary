<?php

declare(strict_types=1);

namespace src\personal\application;

use src\personal\domain\contracts\BancoImportRepository;
use src\personal\domain\services\SugeridorCategoriaBanco;

final class ListarPendientesBanco
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly BancoImportRepository $filas,
        private readonly AsegurarPlanPersonal $plan,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $this->plan->ejecutar($ctx->centroId, $ctx->personaId);
        $hist = $this->filas->historialCategorizado($ctx->personaId);

        return $this->conSugerencias($this->filas->deCuentas($ctx->personaId, [
            AsegurarPlanPersonal::CODIGO_PENDIENTE_GASTO,
            AsegurarPlanPersonal::CODIGO_PENDIENTE_INGRESO,
        ]), $hist);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function otras(): array
    {
        $ctx = $this->ambito->ejecutar();
        $this->plan->ejecutar($ctx->centroId, $ctx->personaId);

        return $this->filas->deCuentas($ctx->personaId, [
            AsegurarPlanPersonal::CODIGO_OTRA_GASTO,
            AsegurarPlanPersonal::CODIGO_OTRA_INGRESO,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @param list<array{concepto:string, cuenta_id:int, tipo:string}> $historial
     * @return list<array<string, mixed>>
     */
    private function conSugerencias(array $filas, array $historial): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $fila['sugerida_id'] = SugeridorCategoriaBanco::sugerir(
                (string) ($fila['concepto'] ?? ''),
                (string) ($fila['sentido'] ?? ''),
                $historial,
            );
            $out[] = $fila;
        }

        return $out;
    }
}
