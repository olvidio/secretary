<?php

declare(strict_types=1);

namespace src\disponible\infrastructure\http;

use InvalidArgumentException;
use src\disponible\application\AjustarDisponible;
use src\disponible\application\ConfirmarAsignacionLabores;
use src\disponible\application\GuardarTramosDesgravacion;
use src\disponible\application\ListarAsignacionesPersona;
use src\disponible\application\ListarDisponibles;
use src\disponible\application\ObtenerTramosDesgravacion;
use src\disponible\application\ProponerDestinoLabores;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class DisponibleController
{
    public function __construct(
        private readonly ListarDisponibles $listar,
        private readonly AjustarDisponible $ajustar,
        private readonly ProponerDestinoLabores $proponer,
        private readonly ConfirmarAsignacionLabores $confirmar,
        private readonly ObtenerTramosDesgravacion $obtenerTramos,
        private readonly GuardarTramosDesgravacion $guardarTramos,
        private readonly ListarAsignacionesPersona $asignacionesPersona,
    ) {
    }

    public function listar(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['personas' => $this->listar->ejecutar()]);
    }

    public function ajustar(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok(['saldo' => $this->ajustar->ejecutar($request->json())]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function proponer(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->proponer->ejecutar());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function confirmar(Request $request, array $vars): Response
    {
        try {
            return ContestarJson::ok(['asignacion' => $this->confirmar->ejecutar((int) ($vars['id'] ?? 0))]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function tramos(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->obtenerTramos->ejecutar());
    }

    public function guardarTramos(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->guardarTramos->ejecutar($request->json()));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function yoAsignaciones(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->asignacionesPersona->ejecutar());
    }
}
