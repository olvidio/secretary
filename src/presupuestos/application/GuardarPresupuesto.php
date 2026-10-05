<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\conceptos\application\ResolverConceptosCentro;
use src\plan\domain\services\CatalogoPlanesContables;
use src\presupuestos\domain\contracts\PresupuestoRepository;
use src\presupuestos\domain\contracts\PresupuestoSgRepository;
use src\presupuestos\domain\entity\LineaPresupuesto;
use src\shared\domain\value_objects\Dinero;

final class GuardarPresupuesto
{
    public function __construct(
        private readonly PresupuestoRepository $repo,
        private readonly ResolverConceptosCentro $conceptos,
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly PresupuestoSgRepository $presupuestoSg,
    ) {
    }

    /**
     * @param array<string, mixed> $lineas codigo => previsto
     */
    public function ejecutar(string $cuenta, array $lineas): void
    {
        $centroId = $this->ambito->ejecutar()->centroId;
        $propio = $this->esCentroSg($centroId) && strtoupper($cuenta) === 'G';
        foreach ($lineas as $codigo => $previsto) {
            $codigo = (string) $codigo;
            if ($this->conceptos->buscar($centroId, $cuenta, $codigo) === null) {
                continue;
            }
            $imp = $previsto === '' || $previsto === null ? Dinero::zero() : Dinero::fromInput((string) $previsto);
            $linea = new LineaPresupuesto($cuenta, $codigo, $imp);
            if ($propio) {
                $this->presupuestoSg->guardar($centroId, $linea);
            } else {
                $this->repo->guardar($linea);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function listar(string $cuenta): array
    {
        $centroId = $this->ambito->ejecutar()->centroId;
        $guardadas = $this->esCentroSg($centroId) && strtoupper($cuenta) === 'G'
            ? $this->presupuestoSg->listar($centroId)
            : $this->repo->listar($cuenta);
        $index = [];
        foreach ($guardadas as $l) {
            $index[$l->conceptoCodigo] = $l;
        }
        $out = [];
        foreach ($this->conceptos->listar($this->ambito->ejecutar()->centroId, $cuenta) as $c) {
            $linea = $index[$c['codigo']] ?? new LineaPresupuesto($cuenta, $c['codigo'], Dinero::zero());
            $out[] = $linea->toArray() + ['nombre' => $c['nombre']];
        }

        return $out;
    }

    private function esCentroSg(int $centroId): bool
    {
        $centro = $this->centros->porId($centroId);

        return $centro !== null && CatalogoPlanesContables::esCentroSg($centro->planContableCodigo);
    }
}
