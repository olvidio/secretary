<?php

declare(strict_types=1);

namespace src\listados\infrastructure\http;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\listados\infrastructure\persistence\PdoListadoRepository;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class ListadoController
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly PdoListadoRepository $listados,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        try {
            $ctx = $this->ambito->ejecutar();

            return ContestarJson::ok([
                'listados' => $this->listados->listar($ctx->centroId),
                'cuentas' => $this->listados->cuentas($ctx->centroId),
                'tesoreria' => $this->listados->tesoreria($ctx->centroId),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function save(Request $request, array $vars = []): Response
    {
        try {
            $ctx = $this->ambito->ejecutar();
            $listado = $this->listados->guardar($ctx->centroId, $request->json());

            return ContestarJson::ok(['listado' => $listado, 'listados' => $this->listados->listar($ctx->centroId)]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function delete(Request $request, array $vars = []): Response
    {
        try {
            $ctx = $this->ambito->ejecutar();
            $this->listados->borrar($ctx->centroId, (int) ($vars['id'] ?? 0));

            return ContestarJson::ok(['listados' => $this->listados->listar($ctx->centroId)]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function run(Request $request, array $vars = []): Response
    {
        try {
            $ctx = $this->ambito->ejecutar();

            return ContestarJson::ok($this->listados->ejecutar($ctx->centroId, (int) ($vars['id'] ?? 0)));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
