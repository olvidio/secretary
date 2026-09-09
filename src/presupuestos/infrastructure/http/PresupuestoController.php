<?php

declare(strict_types=1);

namespace src\presupuestos\infrastructure\http;

use InvalidArgumentException;
use src\presupuestos\application\GuardarPresupuesto;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class PresupuestoController
{
    public function __construct(private readonly GuardarPresupuesto $presupuesto)
    {
    }

    public function get(Request $request, array $vars): Response
    {
        $cuenta = strtoupper((string) ($vars['cuenta'] ?? 'P'));
        return ContestarJson::ok(['lineas' => $this->presupuesto->listar($cuenta)]);
    }

    public function save(Request $request, array $vars): Response
    {
        try {
            $cuenta = strtoupper((string) ($vars['cuenta'] ?? 'P'));
            $this->presupuesto->ejecutar($cuenta, $request->json()['lineas'] ?? []);
            return ContestarJson::ok(['lineas' => $this->presupuesto->listar($cuenta)]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
