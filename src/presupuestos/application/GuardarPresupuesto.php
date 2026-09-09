<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use src\conceptos\domain\contracts\ConceptoRepository;
use src\presupuestos\domain\contracts\PresupuestoRepository;
use src\presupuestos\domain\entity\LineaPresupuesto;
use src\shared\domain\value_objects\Dinero;

final class GuardarPresupuesto
{
    public function __construct(
        private readonly PresupuestoRepository $repo,
        private readonly ConceptoRepository $conceptos,
    ) {
    }

    /**
     * @param array<string, mixed> $lineas codigo => previsto
     */
    public function ejecutar(string $cuenta, array $lineas): void
    {
        foreach ($lineas as $codigo => $previsto) {
            $codigo = (string) $codigo;
            if ($this->conceptos->buscar($cuenta, $codigo) === null) {
                continue;
            }
            $imp = $previsto === '' || $previsto === null ? Dinero::zero() : Dinero::fromInput((string) $previsto);
            $this->repo->guardar(new LineaPresupuesto($cuenta, $codigo, $imp));
        }
    }

    /** @return list<array<string, mixed>> */
    public function listar(string $cuenta): array
    {
        $index = [];
        foreach ($this->repo->listar($cuenta) as $l) {
            $index[$l->conceptoCodigo] = $l;
        }
        $out = [];
        foreach ($this->conceptos->listar($cuenta) as $c) {
            $linea = $index[$c->codigo] ?? new LineaPresupuesto($cuenta, $c->codigo, Dinero::zero());
            $out[] = $linea->toArray() + ['nombre' => $c->nombre];
        }

        return $out;
    }
}
