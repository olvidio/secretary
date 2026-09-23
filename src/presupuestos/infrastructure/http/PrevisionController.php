<?php

declare(strict_types=1);

namespace src\presupuestos\infrastructure\http;

use InvalidArgumentException;
use src\presupuestos\application\AplicarPrevisionAlPresupuesto;
use src\presupuestos\application\GuardarPrevisionPersonal;
use src\presupuestos\application\ObtenerPrevisionConsolidada;
use src\presupuestos\application\ObtenerPrevisionPersonal;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class PrevisionController
{
    public function __construct(
        private readonly ObtenerPrevisionPersonal $personal,
        private readonly GuardarPrevisionPersonal $guardarPersonal,
        private readonly ObtenerPrevisionConsolidada $consolidada,
        private readonly AplicarPrevisionAlPresupuesto $aplicar,
    ) {
    }

    public function opcionesPersonal(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->personal->opciones());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }

    public function getPersonal(Request $request, array $vars): Response
    {
        try {
            return ContestarJson::ok($this->personal->ejecutar(
                (int) ($vars['personaId'] ?? 0),
                self::etiquetaDe($request, null),
            ));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }

    public function savePersonal(Request $request, array $vars): Response
    {
        try {
            $body = $request->json();

            return ContestarJson::ok(
                $this->guardarPersonal->ejecutar(
                    (int) ($vars['personaId'] ?? 0),
                    $body['lineas'] ?? [],
                    self::etiquetaDe($request, $body),
                )
            );
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

    public function getConsolidada(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->consolidada->ejecutar());
    }

    public function aplicarPresupuesto(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->aplicar->ejecutar());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
