<?php

declare(strict_types=1);

namespace src\listados\infrastructure\http;

use InvalidArgumentException;
use src\listados\application\ObtenerDonativosFundacionSg;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class DonativosFundacionSgController
{
    public function __construct(
        private readonly ObtenerDonativosFundacionSg $obtener,
    ) {
    }

    public function get(Request $request, array $vars = []): Response
    {
        try {
            $concepto = $request->query('concepto');
            $codigo = $concepto !== null && $concepto !== '' ? (string) $concepto : null;

            return ContestarJson::ok($this->obtener->ejecutar($codigo));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
