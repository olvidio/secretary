<?php

declare(strict_types=1);

namespace src\cierre\infrastructure\http;

use src\cierre\application\CerrarMes;
use src\cierre\application\SaldarDeudaVivienda;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class CierreController
{
    public function __construct(
        private readonly CerrarMes $cerrar,
        private readonly SaldarDeudaVivienda $saldarDeuda,
    ) {
    }

    public function preview(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->cerrar->ejecutar(false));
    }

    public function run(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->cerrar->ejecutar(true));
    }

    public function regularizar(Request $request, array $vars = []): Response
    {
        try {
            $body = $request->json();
            $meses = isset($body['meses']) && is_array($body['meses']) ? $body['meses'] : null;

            return ContestarJson::ok($this->cerrar->regularizar($meses));
        } catch (\InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function saldarDeuda(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->saldarDeuda->ejecutar($request->json()));
        } catch (\InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
