<?php

declare(strict_types=1);

namespace src\configuracion\infrastructure\http;

use InvalidArgumentException;
use src\configuracion\application\GuardarConfiguracion;
use src\configuracion\application\ObtenerConfiguracion;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class ConfiguracionController
{
    public function __construct(
        private readonly ObtenerConfiguracion $obtener,
        private readonly GuardarConfiguracion $guardar,
    ) {
    }

    public function get(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['config' => $this->obtener->ejecutar()]);
    }

    public function save(Request $request, array $vars = []): Response
    {
        try {
            $cfg = $this->guardar->ejecutar($request->json());
            return ContestarJson::ok(['config' => $cfg->toArray()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
