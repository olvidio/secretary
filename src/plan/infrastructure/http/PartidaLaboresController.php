<?php

declare(strict_types=1);

namespace src\plan\infrastructure\http;

use InvalidArgumentException;
use src\plan\application\GuardarPartidasLabores;
use src\plan\application\ListarPartidasLabores;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class PartidaLaboresController
{
    public function __construct(
        private readonly ListarPartidasLabores $listar,
        private readonly GuardarPartidasLabores $guardar,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->listar->ejecutar());
        } catch (\RuntimeException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }

    public function save(Request $request, array $vars = []): Response
    {
        try {
            $partidas = $this->guardar->ejecutar($request->json());

            return ContestarJson::ok(['partidas' => $partidas]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
