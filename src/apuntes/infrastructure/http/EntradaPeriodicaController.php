<?php

declare(strict_types=1);

namespace src\apuntes\infrastructure\http;

use InvalidArgumentException;
use src\apuntes\application\BorrarEntradaPeriodica;
use src\apuntes\application\EjecutarEntradasPeriodicas;
use src\apuntes\application\GuardarEntradaPeriodica;
use src\apuntes\application\ListarEntradasPeriodicas;
use src\apuntes\application\ListarPendientesEntradaPeriodica;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class EntradaPeriodicaController
{
    public function __construct(
        private readonly ListarEntradasPeriodicas $listar,
        private readonly GuardarEntradaPeriodica $guardar,
        private readonly BorrarEntradaPeriodica $borrar,
        private readonly ListarPendientesEntradaPeriodica $pendientes,
        private readonly EjecutarEntradasPeriodicas $ejecutarPendientes,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok(['entradas' => $this->listar->ejecutar()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function save(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok(['entrada' => $this->guardar->ejecutar($request->json())]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function delete(Request $request, array $vars = []): Response
    {
        try {
            $this->borrar->ejecutar((int) ($vars['id'] ?? 0));

            return ContestarJson::ok([]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function pendientes(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok([
                'pendientes' => $this->pendientes->ejecutar($request->query('hasta')),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function ejecutar(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->ejecutarPendientes->ejecutar($request->json()));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
