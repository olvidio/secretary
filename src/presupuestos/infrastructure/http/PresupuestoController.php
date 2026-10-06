<?php

declare(strict_types=1);

namespace src\presupuestos\infrastructure\http;

use InvalidArgumentException;
use src\presupuestos\application\GuardarPresupuesto;
use src\presupuestos\application\ObtenerPresupuesto;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class PresupuestoController
{
    public function __construct(
        private readonly ObtenerPresupuesto $obtener,
        private readonly GuardarPresupuesto $presupuesto,
    ) {
    }

    public function get(Request $request, array $vars): Response
    {
        try {
            $cuenta = strtoupper((string) ($vars['cuenta'] ?? 'P'));

            return ContestarJson::ok($this->obtener->ejecutar($cuenta, self::etiquetaDe($request, null)));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }

    public function save(Request $request, array $vars): Response
    {
        try {
            $cuenta = strtoupper((string) ($vars['cuenta'] ?? 'P'));
            $datos = $request->json();
            $etiqueta = self::etiquetaDe($request, $datos);
            $this->presupuesto->ejecutar($cuenta, $datos['lineas'] ?? [], $etiqueta);

            return ContestarJson::ok($this->obtener->ejecutar($cuenta, $etiqueta));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    /** @param array<string, mixed>|null $body */
    private static function etiquetaDe(Request $request, ?array $body): ?string
    {
        foreach ([$body['etiqueta'] ?? null, $request->query('etiqueta'), $body['anio'] ?? null, $request->query('anio')] as $raw) {
            if (!is_scalar($raw)) {
                continue;
            }
            $etiqueta = trim((string) $raw);
            if ($etiqueta !== '') {
                return $etiqueta;
            }
        }

        return null;
    }
}
