<?php

declare(strict_types=1);

namespace src\configuracion\infrastructure\http;

use InvalidArgumentException;
use src\configuracion\application\GuardarConfiguracion;
use src\configuracion\application\ObtenerConfiguracion;
use src\plan\domain\contracts\PlanContableRepository;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class ConfiguracionController
{
    public function __construct(
        private readonly ObtenerConfiguracion $obtener,
        private readonly GuardarConfiguracion $guardar,
        private readonly PlanContableRepository $planes,
    ) {
    }

    public function get(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok([
            'config' => $this->obtener->ejecutar(),
            'planes' => $this->planes->listar(),
        ]);
    }

    public function save(Request $request, array $vars = []): Response
    {
        try {
            $this->guardar->ejecutar($request->json());

            return ContestarJson::ok([
                'config' => $this->obtener->ejecutar(),
                'planes' => $this->planes->listar(),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
