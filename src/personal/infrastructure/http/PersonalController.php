<?php

declare(strict_types=1);

namespace src\personal\infrastructure\http;

use InvalidArgumentException;
use src\personal\application\BorrarMovimientoPersonal;
use src\personal\application\CrearSubcuentaPersonal;
use src\personal\application\ListarCategoriasPersonales;
use src\personal\application\ListarMovimientosPersonales;
use src\personal\application\RegistrarMovimientoPersonal;
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
        private readonly BorrarMovimientoPersonal $borrar,
        private readonly ListarCategoriasPersonales $categorias,
        private readonly CrearSubcuentaPersonal $crearSubcuenta,
    ) {
    }

    public function resumen(Request $request, array $vars = []): Response
    {
        [$desde, $hasta, $anio, $mes] = $this->mes($request);
        $datos = $this->resumen->ejecutar($desde, $hasta);
        $datos['anio'] = $anio;
        $datos['mes'] = $mes;

        return ContestarJson::ok($datos);
    }

    public function movimientos(Request $request, array $vars = []): Response
    {
        [$desde, $hasta] = $this->mes($request);

        return ContestarJson::ok(['movimientos' => $this->listar->ejecutar($desde, $hasta)]);
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

    public function crearCategoria(Request $request, array $vars = []): Response
    {
        try {
            $cuenta = $this->crearSubcuenta->ejecutar($request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['cuenta' => $cuenta->toArray()]);
    }

    /** @return array{0:string,1:string,2:int,3:int} */
    private function mes(Request $request): array
    {
        $anio = (int) ($request->query('anio') ?? date('Y'));
        $mes = (int) ($request->query('mes') ?? date('n'));
        if ($mes < 1 || $mes > 12 || $anio < 1990 || $anio > 2100) {
            $anio = (int) date('Y');
            $mes = (int) date('n');
        }
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $ultimo = (int) date('t', (int) strtotime($desde));

        return [$desde, sprintf('%04d-%02d-%02d', $anio, $mes, $ultimo), $anio, $mes];
    }
}
