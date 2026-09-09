<?php

declare(strict_types=1);

namespace src\apuntes\infrastructure\http;

use InvalidArgumentException;
use src\apuntes\application\BorrarApunte;
use src\apuntes\application\BuscarSugerenciasObservacion;
use src\apuntes\application\CalcularCuadreApuntesA;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\ListarApuntes;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class ApunteController
{
    public function __construct(
        private readonly ListarApuntes $listar,
        private readonly CrearApunte $crear,
        private readonly BorrarApunte $borrar,
        private readonly BuscarSugerenciasObservacion $sugerenciasObs,
        private readonly CalcularCuadreApuntesA $cuadreApuntesA,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        $filtros = array_filter([
            'cuenta' => $request->query('cuenta'),
            'concepto' => $request->query('concepto'),
            'iniciales' => $request->query('iniciales'),
            'origen' => $request->query('origen'),
            'desde' => $request->query('desde'),
            'hasta' => $request->query('hasta'),
        ], static fn ($v) => $v !== null && $v !== '');

        return ContestarJson::ok(['apuntes' => $this->listar->ejecutar($filtros)]);
    }

    public function cuadre(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok([
            'cuadre' => $this->cuadreApuntesA->ejecutar(
                (string) ($request->query('cuenta') ?? 'P'),
                (string) ($request->query('iniciales') ?? ''),
                (string) ($request->query('fecha') ?? ''),
            ),
        ]);
    }

    public function sugerencias(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok([
                'sugerencias' => $this->sugerenciasObs->ejecutar(
                    (string) ($request->query('q') ?? ''),
                    (string) ($request->query('cuenta') ?? 'P'),
                    (string) ($request->query('iniciales') ?? ''),
                ),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function create(Request $request, array $vars = []): Response
    {
        try {
            $creados = $this->crear->ejecutar($request->json());
            $arr = [];
            foreach ($creados as $a) {
                $arr[] = $a->toArray();
            }
            return ContestarJson::ok(['apuntes' => $arr]);
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
