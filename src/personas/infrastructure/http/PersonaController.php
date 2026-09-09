<?php

declare(strict_types=1);

namespace src\personas\infrastructure\http;

use InvalidArgumentException;
use src\personas\application\GuardarPersona;
use src\personas\application\ListarPersonas;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class PersonaController
{
    public function __construct(
        private readonly ListarPersonas $listar,
        private readonly GuardarPersona $guardar,
        private readonly PersonaRepository $repo,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['personas' => $this->listar->ejecutar()]);
    }

    public function save(Request $request, array $vars = []): Response
    {
        try {
            $p = $this->guardar->ejecutar($request->json());
            return ContestarJson::ok(['persona' => $p->toArray()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function delete(Request $request, array $vars): Response
    {
        $this->repo->borrar((int) $vars['id']);
        return ContestarJson::ok();
    }
}
