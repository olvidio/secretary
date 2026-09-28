<?php

declare(strict_types=1);

namespace src\plan\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\plan\application\GuardarDestinosSg;
use src\plan\application\ListarDestinosSg;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class DestinosSgController
{
    public function __construct(
        private readonly ListarDestinosSg $listar,
        private readonly GuardarDestinosSg $guardar,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->listar->ejecutar());
        } catch (RuntimeException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }

    public function save(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->guardar->ejecutar($request->json()));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        } catch (RuntimeException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }
}
