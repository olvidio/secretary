<?php

declare(strict_types=1);

namespace src\cierre\infrastructure\http;

use src\cierre\application\CerrarMes;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class CierreController
{
    public function __construct(private readonly CerrarMes $cerrar)
    {
    }

    public function preview(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->cerrar->ejecutar(false));
    }

    public function run(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->cerrar->ejecutar(true));
    }
}
