<?php

declare(strict_types=1);

namespace src\presupuestos\infrastructure\http;

use InvalidArgumentException;
use src\presupuestos\application\GuardarPresupuesto;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class PresupuestoController
{
    public function __construct(private readonly GuardarPresupuesto $presupuesto)
    {
    }

    public function get(Request $request, array $vars): Response
    {
        $cuenta = strtoupper((string) ($vars['cuenta'] ?? 'P'));
        $out = ['lineas' => $this->presupuesto->listar($cuenta)];
        $numS = $this->presupuesto->numS($cuenta);
        if ($numS !== null) {
            $out['num_s'] = $numS;
        }

        return ContestarJson::ok($out);
    }

    public function save(Request $request, array $vars): Response
    {
        try {
            $cuenta = strtoupper((string) ($vars['cuenta'] ?? 'P'));
            $datos = $request->json();
            $numS = array_key_exists('num_s', $datos) ? (int) $datos['num_s'] : null;
            $this->presupuesto->ejecutar($cuenta, $datos['lineas'] ?? [], $numS);
            $out = ['lineas' => $this->presupuesto->listar($cuenta)];
            $guardado = $this->presupuesto->numS($cuenta);
            if ($guardado !== null) {
                $out['num_s'] = $guardado;
            }

            return ContestarJson::ok($out);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
