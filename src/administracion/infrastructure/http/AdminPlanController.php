<?php

declare(strict_types=1);

namespace src\administracion\infrastructure\http;

use InvalidArgumentException;
use src\administracion\application\EliminarPlanContable;
use src\administracion\application\GuardarConceptosPlan;
use src\administracion\application\GuardarPlanContable;
use src\plan\domain\contracts\PlanContableRepository;
use src\plan\domain\contracts\PlanConceptoRepository;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AdminPlanController
{
    public function __construct(
        private readonly PlanContableRepository $planes,
        private readonly GuardarPlanContable $guardar,
        private readonly EliminarPlanContable $eliminar,
        private readonly PlanConceptoRepository $planConceptos,
        private readonly GuardarConceptosPlan $guardarConceptos,
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
        $planId = (int) ($vars['id'] ?? 0);
        $cuenta = $request->query('cuenta');
        $lista = $this->planConceptos->listar($planId, $cuenta !== null && $cuenta !== '' ? (string) $cuenta : null);

        return ContestarJson::ok([
            'conceptos' => array_map(static fn ($c) => $c->toArray(), $lista),
        ]);
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
