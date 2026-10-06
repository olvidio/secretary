<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use InvalidArgumentException;
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
        private readonly ConstruirHojaPrevision $hoja,
    ) {
    }

    /**
     * @param array<string, mixed> $lineas codigo => previsto
     */
    public function ejecutar(string $cuenta, array $lineas, ?string $etiquetaObjetivo = null): void
    {
        $ejercicioId = $this->resolverEjercicioId($etiquetaObjetivo, true);
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
                $this->presupuestoSg->guardar($centroId, $ejercicioId, $linea);
            } else {
                $this->repo->guardar($ejercicioId, $linea);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function listarPorEjercicio(string $cuenta, ?int $ejercicioId): array
    {
        $centroId = $this->ambito->ejecutar()->centroId;
        $cuenta = strtoupper($cuenta);
        $guardadas = [];
        if ($ejercicioId !== null) {
            $guardadas = $this->esCentroSg($centroId) && $cuenta === 'G'
                ? $this->presupuestoSg->listar($centroId, $ejercicioId)
                : $this->repo->listar($cuenta, $ejercicioId);
        }
        $index = [];
        foreach ($guardadas as $l) {
            $index[$l->conceptoCodigo] = $l;
        }
        $out = [];
        foreach ($this->conceptos->listar($centroId, $cuenta) as $c) {
            $linea = $index[$c['codigo']] ?? new LineaPresupuesto($cuenta, $c['codigo'], Dinero::zero());
            $out[] = $linea->toArray() + ['nombre' => $c['nombre']];
        }

        return $out;
    }

    private function resolverEjercicioId(?string $etiquetaObjetivo, bool $asegurarAlGuardar): int
    {
        $opts = $this->hoja->opcionesPrevision();
        $etiqueta = trim((string) ($etiquetaObjetivo ?? ''));
        if ($etiqueta === '') {
            $etiqueta = (string) ($opts['etiqueta_trabajo'] ?? $opts['etiqueta_defecto']);
        }
        $permitidas = $opts['etiquetas'];
        if (!in_array($etiqueta, $permitidas, true)) {
            throw new InvalidArgumentException(_("No hay ejercicio para el año elegido"));
        }
        if ($asegurarAlGuardar) {
            $ejercicio = $this->hoja->asegurarEjercicioPrevision($etiqueta);
        } else {
            $ejercicio = $this->hoja->ejercicioPorEtiqueta($etiqueta);
        }
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new InvalidArgumentException(_("No hay ejercicio para el año elegido"));
        }

        return $ejercicio->id;
    }

    private function esCentroSg(int $centroId): bool
    {
        $centro = $this->centros->porId($centroId);

        return $centro !== null && CatalogoPlanesContables::esCentroSg($centro->planContableCodigo);
    }
}
