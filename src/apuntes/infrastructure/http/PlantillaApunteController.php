<?php

declare(strict_types=1);

namespace src\apuntes\infrastructure\http;

use InvalidArgumentException;
use src\apuntes\application\BorrarPlantillaApunte;
use src\apuntes\application\GuardarPlantillaApunte;
use src\apuntes\application\ListarPlantillasApunte;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class PlantillaApunteController
{
    public function __construct(
        private readonly ListarPlantillasApunte $listar,
        private readonly GuardarPlantillaApunte $guardar,
        private readonly BorrarPlantillaApunte $borrar,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok([
            'plantillas' => $this->listar->ejecutar((string) ($request->query('cuenta') ?? 'P')),
        ]);
    }

    public function save(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok(['plantilla' => $this->guardar->ejecutar($request->json())]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function delete(Request $request, array $vars): Response
    {
        try {
            $this->borrar->ejecutar((int) $vars['id']);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }

        return ContestarJson::ok();
    }
}
