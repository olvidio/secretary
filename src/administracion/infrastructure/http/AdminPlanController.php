<?php

declare(strict_types=1);

namespace src\administracion\infrastructure\http;

use InvalidArgumentException;
use src\administracion\application\EliminarPlanContable;
use src\administracion\application\ExportarConceptosPlan;
use src\administracion\application\GuardarConceptosPlan;
use src\administracion\application\GuardarPlanContable;
use src\administracion\application\ImportarConceptosPlan;
use src\administracion\application\ObtenerConceptosPlan;
use src\plan\domain\contracts\PlanContableRepository;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AdminPlanController
{
    public function __construct(
        private readonly PlanContableRepository $planes,
        private readonly GuardarPlanContable $guardar,
        private readonly EliminarPlanContable $eliminar,
        private readonly ObtenerConceptosPlan $obtenerConceptos,
        private readonly GuardarConceptosPlan $guardarConceptos,
        private readonly ExportarConceptosPlan $exportarConceptos,
        private readonly ImportarConceptosPlan $importarConceptos,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['planes' => $this->planes->listar()]);
    }

    public function save(Request $request, array $vars = []): Response
    {
        try {
            $plan = $this->guardar->ejecutar($request->json());

            return ContestarJson::ok(['plan' => $plan, 'planes' => $this->planes->listar()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function delete(Request $request, array $vars = []): Response
    {
        try {
            $this->eliminar->ejecutar((int) ($vars['id'] ?? 0));

            return ContestarJson::ok(['planes' => $this->planes->listar()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function conceptos(Request $request, array $vars = []): Response
    {
        try {
            $planId = (int) ($vars['id'] ?? 0);
            $cuenta = $request->query('cuenta');
            $cuenta = $cuenta !== null && $cuenta !== '' ? (string) $cuenta : null;

            return ContestarJson::ok([
                'conceptos' => $this->obtenerConceptos->ejecutar($planId, $cuenta),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function exportConceptos(Request $request, array $vars = []): Response
    {
        try {
            $planId = (int) ($vars['id'] ?? 0);
            $payload = $this->exportarConceptos->ejecutar($planId);
            $codigo = preg_replace('/[^A-Za-z0-9._-]+/', '_', $payload['plan']['codigo']) ?: 'plan';
            $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new InvalidArgumentException(_("No se pudo generar el fichero"));
            }

            return new Response(
                $json . "\n",
                200,
                [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="plan-' . $codigo . '-conceptos.json"',
                ],
            );
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function importConceptos(Request $request, array $vars = []): Response
    {
        try {
            $planId = (int) ($vars['id'] ?? 0);
            $conceptos = $this->importarConceptos->ejecutar($planId, $request->json());

            return ContestarJson::ok(['conceptos' => $conceptos]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function saveConceptos(Request $request, array $vars = []): Response
    {
        try {
            $planId = (int) ($vars['id'] ?? 0);
            $conceptos = $this->guardarConceptos->ejecutar($planId, $request->json());

            return ContestarJson::ok(['conceptos' => $conceptos]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
