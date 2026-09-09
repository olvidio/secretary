<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\http;

use InvalidArgumentException;
use src\ambito\application\CrearCuentaFisica;
use src\ambito\application\DesactivarCuentaFisica;
use src\ambito\application\ListarCuentasFisicas;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class TesoreriaController
{
    public function __construct(
        private readonly ListarCuentasFisicas $listar,
        private readonly CrearCuentaFisica $crear,
        private readonly DesactivarCuentaFisica $desactivar,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['cuentas_fisicas' => $this->listar->ejecutar()]);
    }

    public function create(Request $request, array $vars = []): Response
    {
        try {
            $resultado = $this->crear->ejecutar($request->json());

            return ContestarJson::ok([
                'fisica' => $resultado['fisica']->toArray(),
                'cuentas' => array_map(static fn ($c) => $c->toArray(), $resultado['cuentas']),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function desactivar(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        try {
            $fisica = $this->desactivar->ejecutar($id);

            return ContestarJson::ok(['fisica' => $fisica->toArray()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
