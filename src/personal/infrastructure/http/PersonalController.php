<?php

declare(strict_types=1);

namespace src\personal\infrastructure\http;

use InvalidArgumentException;
use src\personal\application\ActualizarMovimientoPersonal;
use src\personal\application\BorrarCierrePersonalMes;
use src\personal\application\BorrarMovimientoPersonal;
use src\personal\application\DesdoblarMovimientoPersonal;
use src\personal\application\CrearSubcuentaPersonal;
use src\personal\application\GuardarCierrePersonalDefecto;
use src\personal\application\GuardarCierrePersonalMes;
use src\personal\application\ListarCategoriasPersonales;
use src\personal\application\ListarConceptosGenerales;
use src\personal\application\ListarMovimientosPersonales;
use src\personal\application\RegistrarMovimientoPersonal;
use src\personal\application\ResolverPeriodoPersonal;
use src\personal\application\ResolverPersonaActual;
use src\personal\application\ResumenMensualPersonal;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class PersonalController
{
    public function __construct(
        private readonly ResumenMensualPersonal $resumen,
        private readonly ListarMovimientosPersonales $listar,
        private readonly RegistrarMovimientoPersonal $registrar,
        private readonly ActualizarMovimientoPersonal $actualizar,
        private readonly DesdoblarMovimientoPersonal $desdoblar,
        private readonly BorrarMovimientoPersonal $borrar,
        private readonly ListarCategoriasPersonales $categorias,
        private readonly ListarConceptosGenerales $conceptosGenerales,
        private readonly CrearSubcuentaPersonal $crearSubcuenta,
        private readonly ResolverPersonaActual $ambito,
        private readonly ResolverPeriodoPersonal $periodo,
        private readonly GuardarCierrePersonalDefecto $guardarCierreDefecto,
        private readonly GuardarCierrePersonalMes $guardarCierreMes,
        private readonly BorrarCierrePersonalMes $borrarCierreMes,
    ) {
    }

    public function resumen(Request $request, array $vars = []): Response
    {
        $periodo = $this->periodoDe($request);
        $datos = $this->resumen->ejecutar($periodo['desde'], $periodo['hasta']);
        $datos['anio'] = $periodo['anio'];
        $datos['mes'] = $periodo['mes'];
        $datos['fecha_cierre'] = $periodo['fecha_cierre'];

        return ContestarJson::ok($datos);
    }

    public function movimientos(Request $request, array $vars = []): Response
    {
        $periodo = $this->periodoDe($request);

        return ContestarJson::ok([
            'movimientos' => $this->listar->ejecutar($periodo['desde'], $periodo['hasta']),
            'fecha_cierre' => $periodo['fecha_cierre'],
        ]);
    }

    public function cierre(Request $request, array $vars = []): Response
    {
        $periodo = $this->periodoDe($request);

        return ContestarJson::ok($periodo);
    }

    public function guardarCierreDefecto(Request $request, array $vars = []): Response
    {
        try {
            $datos = $this->guardarCierreDefecto->ejecutar($request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok($datos);
    }

    public function guardarCierreMes(Request $request, array $vars = []): Response
    {
        try {
            $datos = $this->guardarCierreMes->ejecutar($request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok($datos);
    }

    public function borrarCierreMes(Request $request, array $vars = []): Response
    {
        try {
            $datos = $this->borrarCierreMes->ejecutar($request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok($datos);
    }

    public function crear(Request $request, array $vars = []): Response
    {
        try {
            $guardados = $this->registrar->ejecutar($request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }
        $ids = [];
        foreach ($guardados as $a) {
            $ids[] = $a->id;
        }

        return ContestarJson::ok(['ids' => $ids]);
    }

    public function actualizarMovimiento(Request $request, array $vars = []): Response
    {
        try {
            $guardados = $this->actualizar->ejecutar((int) ($vars['id'] ?? 0), $request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }
        $ids = [];
        foreach ($guardados as $a) {
            $ids[] = $a->id;
        }

        return ContestarJson::ok(['ids' => $ids]);
    }

    public function desdoblarMovimiento(Request $request, array $vars = []): Response
    {
        try {
            $guardados = $this->desdoblar->ejecutar((int) ($vars['id'] ?? 0), $request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }
        $ids = [];
        foreach ($guardados as $a) {
            $ids[] = $a->id;
        }

        return ContestarJson::ok(['ids' => $ids]);
    }

    public function borrarMovimiento(Request $request, array $vars = []): Response
    {
        try {
            $this->borrar->ejecutar((int) ($vars['id'] ?? 0));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }

        return ContestarJson::ok();
    }

    public function listarCategorias(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->categorias->ejecutar());
    }

    public function listarConceptosGenerales(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['conceptos' => $this->conceptosGenerales->ejecutar()]);
    }

    public function crearCategoria(Request $request, array $vars = []): Response
    {
        try {
            $cuenta = $this->crearSubcuenta->ejecutar($request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['cuenta' => $cuenta->toArray()]);
    }

    /** @return array{anio:int,mes:int,desde:string,hasta:string,fecha_cierre:string} */
    private function periodoDe(Request $request): array
    {
        $anio = (int) ($request->query('anio') ?? date('Y'));
        $mes = (int) ($request->query('mes') ?? date('n'));
        if ($mes < 1 || $mes > 12 || $anio < 1990 || $anio > 2100) {
            $anio = (int) date('Y');
            $mes = (int) date('n');
        }
        $ctx = $this->ambito->ejecutar();
        $p = $this->periodo->ejecutar($ctx->personaId, $anio, $mes);

        return [
            'anio' => $p['anio'],
            'mes' => $p['mes'],
            'desde' => $p['desde'],
            'hasta' => $p['hasta'],
            'fecha_cierre' => $p['fecha_cierre'],
        ];
    }
}
