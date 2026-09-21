<?php

declare(strict_types=1);

namespace src\apuntes\application;

use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\BancoCentroImportRepository;
use src\apuntes\domain\services\SugeridorConceptoBancoCentro;

final class ListarPendientesBancoCentro
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly BancoCentroImportRepository $filas,
        private readonly AsegurarPendienteBancoCentro $pendientes,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $this->pendientes->ejecutar($ctx->centroId);
        $hist = $this->filas->historialCategorizado($ctx->centroId);
        $sinAsentar = $this->filas->sinAsentar($ctx->centroId);
        $legacy = $this->filas->deCuentas($ctx->centroId, [
            AsegurarPendienteBancoCentro::CODIGO_PENDIENTE_GASTO,
            AsegurarPendienteBancoCentro::CODIGO_PENDIENTE_INGRESO,
        ]);

        return $this->conSugerencias(array_merge($sinAsentar, $legacy), $hist);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function otras(): array
    {
        $ctx = $this->ambito->ejecutar();
        $this->pendientes->ejecutar($ctx->centroId);

        return $this->filas->deCuentas($ctx->centroId, [
            AsegurarPendienteBancoCentro::CODIGO_OTRA_GASTO,
            AsegurarPendienteBancoCentro::CODIGO_OTRA_INGRESO,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @param list<array{concepto:string, concepto_codigo:string, tipo:string}> $historial
     * @return list<array<string, mixed>>
     */
    private function conSugerencias(array $filas, array $historial): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $fila['sugerida_codigo'] = SugeridorConceptoBancoCentro::sugerir(
                (string) ($fila['concepto'] ?? ''),
                (string) ($fila['sentido'] ?? ''),
                $historial,
            );
            $out[] = $fila;
        }

        return $out;
    }
}
