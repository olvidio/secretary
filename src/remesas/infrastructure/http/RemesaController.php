<?php

declare(strict_types=1);

namespace src\remesas\infrastructure\http;

use InvalidArgumentException;
use src\remesas\application\AceptarRemesa;
use src\remesas\application\EnviarRemesa;
use src\remesas\application\ListarRemesasCentro;
use src\remesas\application\ListarSolicitudesPersonales;
use src\remesas\application\ObtenerDetalleRemesa;
use src\remesas\application\ObtenerRemesaCentro;
use src\remesas\application\ObtenerRemesaPersonal;
use src\remesas\application\PrevisualizarRemesa;
use src\remesas\application\RechazarRemesa;
use src\remesas\application\ResolverSolicitudDetalle;
use src\remesas\application\SolicitarDetalleRemesa;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class RemesaController
{
    public function __construct(
        private readonly PrevisualizarRemesa $previsualizar,
        private readonly EnviarRemesa $enviar,
        private readonly ObtenerRemesaPersonal $verPersonal,
        private readonly ListarSolicitudesPersonales $solicitudesPersona,
        private readonly ResolverSolicitudDetalle $resolverSolicitud,
        private readonly ListarRemesasCentro $listarCentro,
        private readonly ObtenerRemesaCentro $verCentro,
        private readonly AceptarRemesa $aceptar,
        private readonly RechazarRemesa $rechazar,
        private readonly SolicitarDetalleRemesa $solicitarDetalle,
        private readonly ObtenerDetalleRemesa $detalleLinea,
    ) {
    }

    public function previsualizar(Request $request, array $vars = []): Response
    {
        try {
            [$anio, $mes] = $this->mes($request);

            return ContestarJson::ok($this->previsualizar->ejecutar($anio, $mes));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }
    }

    public function enviar(Request $request, array $vars = []): Response
    {
        try {
            $remesa = $this->enviar->ejecutar($request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['remesa' => $remesa->toArray()]);
    }

    public function verPersonal(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok(['remesa' => $this->verPersonal->ejecutar((int) ($vars['id'] ?? 0))]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }

    public function solicitudesPersona(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['solicitudes' => $this->solicitudesPersona->ejecutar()]);
    }

    public function resolverSolicitud(Request $request, array $vars = []): Response
    {
        try {
            $sol = $this->resolverSolicitud->ejecutar((int) ($vars['id'] ?? 0), $request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['solicitud' => $sol->toArray()]);
    }

    public function listarCentro(Request $request, array $vars = []): Response
    {
        $estado = $request->query('estado');

        return ContestarJson::ok(['remesas' => $this->listarCentro->ejecutar($estado)]);
    }

    public function verCentro(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok(['remesa' => $this->verCentro->ejecutar((int) ($vars['id'] ?? 0))]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }

    public function aceptar(Request $request, array $vars = []): Response
    {
        try {
            $remesa = $this->aceptar->ejecutar((int) ($vars['id'] ?? 0));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['remesa' => $remesa->toArray()]);
    }

    public function rechazar(Request $request, array $vars = []): Response
    {
        try {
            $remesa = $this->rechazar->ejecutar((int) ($vars['id'] ?? 0), $request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['remesa' => $remesa->toArray()]);
    }

    public function solicitarDetalle(Request $request, array $vars = []): Response
    {
        try {
            $sol = $this->solicitarDetalle->ejecutar(
                (int) ($vars['id'] ?? 0),
                (int) ($vars['lineaId'] ?? 0),
            );
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['solicitud' => $sol->toArray()]);
    }

    public function detalleLinea(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok([
                'detalle' => $this->detalleLinea->ejecutar(
                    (int) ($vars['id'] ?? 0),
                    (int) ($vars['lineaId'] ?? 0),
                ),
            ]);
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $status = str_contains($msg, 'autorizado') ? 403 : 404;

            return ContestarJson::error($msg, $status);
        }
    }

    /** @return array{0:int,1:int} */
    private function mes(Request $request): array
    {
        $anio = (int) ($request->query('anio') ?? date('Y'));
        $mes = (int) ($request->query('mes') ?? date('n'));

        return [$anio, $mes];
    }
}
