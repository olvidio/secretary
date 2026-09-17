<?php

declare(strict_types=1);

namespace src\personas\infrastructure\http;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\personas\application\AprobarSolicitudVinculoCentro;
use src\personas\application\ListarCandidatosVinculoCentro;
use src\personas\application\ListarCentrosDisponiblesPersona;
use src\personas\application\ListarSolicitudesVinculoCentro;
use src\personas\application\ListarVinculosPersona;
use src\personas\application\RechazarSolicitudVinculoCentro;
use src\personas\application\SolicitarVinculoCentro;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class VinculoCentroController
{
    public function __construct(
        private readonly SolicitarVinculoCentro $solicitar,
        private readonly ListarVinculosPersona $listarVinculos,
        private readonly ListarCentrosDisponiblesPersona $centrosDisponibles,
        private readonly ListarSolicitudesVinculoCentro $listarSolicitudes,
        private readonly ListarCandidatosVinculoCentro $candidatos,
        private readonly AprobarSolicitudVinculoCentro $aprobar,
        private readonly RechazarSolicitudVinculoCentro $rechazar,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    public function listarYo(Request $request, array $vars = []): Response
    {
        $identidadId = (int) ($_SESSION['identidad_id'] ?? 0);
        if ($identidadId <= 0) {
            return ContestarJson::error(_("No autenticado"), 401);
        }

        return ContestarJson::ok($this->listarVinculos->ejecutar($identidadId));
    }

    public function centrosDisponibles(Request $request, array $vars = []): Response
    {
        $identidadId = (int) ($_SESSION['identidad_id'] ?? 0);
        if ($identidadId <= 0) {
            return ContestarJson::error(_("No autenticado"), 401);
        }

        return ContestarJson::ok([
            'centros' => $this->centrosDisponibles->ejecutar($identidadId),
        ]);
    }

    public function solicitarYo(Request $request, array $vars = []): Response
    {
        try {
            $identidadId = (int) ($_SESSION['identidad_id'] ?? 0);
            if ($identidadId <= 0) {
                return ContestarJson::error(_("No autenticado"), 401);
            }

            return ContestarJson::ok([
                'solicitud' => $this->solicitar->ejecutar($identidadId, $request->json()),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function listarCentro(Request $request, array $vars = []): Response
    {
        $centroId = $this->ambito->ejecutar()->centroId;

        return ContestarJson::ok([
            'solicitudes' => $this->listarSolicitudes->ejecutar($centroId),
        ]);
    }

    public function candidatos(Request $request, array $vars): Response
    {
        try {
            $centroId = $this->ambito->ejecutar()->centroId;

            return ContestarJson::ok([
                'candidatos' => $this->candidatos->ejecutar($centroId, (int) $vars['id']),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }

    public function aprobar(Request $request, array $vars): Response
    {
        try {
            $centroId = $this->ambito->ejecutar()->centroId;
            $resolvedBy = (int) ($_SESSION['identidad_id'] ?? 0);

            return ContestarJson::ok([
                'persona' => $this->aprobar->ejecutar(
                    $centroId,
                    (int) $vars['id'],
                    $resolvedBy,
                    $request->json(),
                ),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function rechazar(Request $request, array $vars): Response
    {
        try {
            $centroId = $this->ambito->ejecutar()->centroId;
            $resolvedBy = (int) ($_SESSION['identidad_id'] ?? 0);
            $this->rechazar->ejecutar($centroId, (int) $vars['id'], $resolvedBy);

            return ContestarJson::ok();
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }
}
