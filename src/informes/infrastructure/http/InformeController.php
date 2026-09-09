<?php

declare(strict_types=1);

namespace src\informes\infrastructure\http;

use src\informes\application\CalcularSaldos;
use src\informes\application\ObtenerE37;
use src\informes\application\ObtenerResumen613;
use src\informes\application\ObtenerSaldosTesoreria;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class InformeController
{
    public function __construct(
        private readonly ObtenerResumen613 $resumen613,
        private readonly ObtenerE37 $e37,
        private readonly CalcularSaldos $saldos,
        private readonly ObtenerSaldosTesoreria $saldosTesoreria,
    ) {
    }

    public function resumen613(Request $request, array $vars): Response
    {
        $cuenta = strtoupper((string) ($vars['cuenta'] ?? 'P'));
        return ContestarJson::ok($this->resumen613->ejecutar($cuenta));
    }

    public function e37(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->e37->detalle($request->query('iniciales')));
    }

    public function e37Resumen(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->e37->resumen());
    }

    public function saldos(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->saldos->ejecutar($request->query('hasta'), $request->query('iniciales')));
    }

    public function tesoreria(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok([
            'hasta' => $request->query('hasta'),
            'cuentas' => $this->saldosTesoreria->ejecutar($request->query('hasta')),
        ]);
    }
}
