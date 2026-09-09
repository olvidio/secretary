<?php

declare(strict_types=1);

namespace src\personal\application;

use src\ambito\domain\contracts\CuentaRepository;

final class ListarCategoriasPersonales
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly CuentaRepository $cuentas,
        private readonly AsegurarPlanPersonal $asegurar,
    ) {
    }

    /**
     * @return array{categorias: list<array<string, mixed>>, tesoreria: list<array<string, mixed>>}
     */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $this->asegurar->ejecutar($ctx->centroId, $ctx->personaId);
        $categorias = [];
        $tesoreria = [];
        foreach ($this->cuentas->listarDePersona($ctx->centroId, $ctx->personaId, 'X') as $c) {
            $fila = [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'nombre' => $c->nombre,
                'tipo' => $c->tipo,
                'codigo_maestro' => $c->codigoMaestro,
                'imputable' => $c->imputable,
                'padre_id' => $c->padreId,
            ];
            if (in_array($c->tipo, ['ingreso', 'gasto'], true)) {
                $categorias[] = $fila;
            }
            if ($c->tipo === 'tesoreria') {
                $tesoreria[] = $fila;
            }
        }

        return ['categorias' => $categorias, 'tesoreria' => $tesoreria];
    }
}
