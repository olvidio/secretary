<?php

declare(strict_types=1);

namespace src\conceptos\infrastructure\http;

use src\conceptos\application\ListarConceptos;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class ConceptoController
{
    public function __construct(private readonly ListarConceptos $listar)
    {
    }

    public function list(Request $request, array $vars = []): Response
    {
        $cuenta = $request->query('cuenta');
        return ContestarJson::ok(['conceptos' => $this->listar->ejecutar($cuenta)]);
    }
}
