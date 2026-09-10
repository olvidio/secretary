<?php

declare(strict_types=1);

namespace src\personas\infrastructure\http;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
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
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        $centroId = $this->ambito->ejecutar()->centroId;

        return ContestarJson::ok(['personas' => $this->listar->ejecutarDeCentro($centroId)]);
    }

    public function save(Request $request, array $vars = []): Response
    {
        try {
            $resultado = $this->guardar->ejecutar($request->json());
            $fila = $resultado['persona']->toArray();
            $fila['email'] = $resultado['persona']->email ?? '';
            $fila['vivienda_aporta_generales'] = $resultado['persona']->viviendaAportaGenerales;
            $payload = ['persona' => $fila];
            if ($resultado['password_inicial'] !== null) {
                $payload['password_inicial'] = $resultado['password_inicial'];
            }

            return ContestarJson::ok($payload);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function delete(Request $request, array $vars): Response
    {
        $id = (int) $vars['id'];
        $persona = $this->repo->porId($id);
        $centroId = $this->ambito->ejecutar()->centroId;
        if ($persona === null || $persona->centroId !== $centroId) {
            return ContestarJson::error('Persona no encontrada en este centro', 404);
        }
        $this->repo->borrar($id);

        return ContestarJson::ok();
    }
}
