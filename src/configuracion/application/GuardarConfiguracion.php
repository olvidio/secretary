<?php

declare(strict_types=1);

namespace src\configuracion\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;
use src\plan\domain\contracts\PlanContableRepository;
use src\ambito\domain\services\TipoEntidad;
use src\plan\domain\services\CatalogoPlanesContables;

final class GuardarConfiguracion
{
    public function __construct(
        private readonly ConfiguracionRepository $repo,
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly PlanContableRepository $planes,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): ConfiguracionCentro
    {
        $actual = $this->repo->get();
        $anio = (int) ($datos['anio'] ?? $actual->anio);
        $modo = (string) ($datos['modo_ejercicio'] ?? $actual->modoEjercicio);
        if (!in_array($modo, ['Año', 'Curso'], true)) {
            throw new InvalidArgumentException(_("Modo: Año o Curso"));
        }
        $centroActivo = $this->centroActivo();
        $esClub = $centroActivo !== null && CatalogoPlanesContables::esClub($centroActivo->planContableCodigo);
        $esCentroSg = $centroActivo !== null && CatalogoPlanesContables::esCentroSg($centroActivo->planContableCodigo);
        $planFijo = $esClub || $esCentroSg;
        $tipoCierre = (string) ($datos['tipo_cierre'] ?? $actual->tipoCierre);
        if ($planFijo && $centroActivo !== null) {
            $tipoCierre = $centroActivo->tipoCierre;
        }
        if (!in_array($tipoCierre, ['vivienda', 'necesidades'], true)) {
            throw new InvalidArgumentException(_("Tipo de cierre: vivienda o necesidades"));
        }
        $tipo = strtolower(trim((string) ($datos['tipo'] ?? '')));
        if ($planFijo && $centroActivo !== null) {
            $tipo = $centroActivo->tipo;
        } elseif ($tipo === '') {
            try {
                $centro = $this->centros->porId($this->ambito->ejecutar()->centroId);
                $tipo = $centro?->tipo ?? 'n';
            } catch (\Throwable) {
                $tipo = 'n';
            }
        }
        if (!in_array($tipo, TipoEntidad::deAlta(), true)) {
            throw new InvalidArgumentException(_("Tipo de entidad no válido"));
        }
        if (!$planFijo && !in_array($tipo, [TipoEntidad::CENTRO_N, TipoEntidad::CENTRO_SG], true)) {
            throw new InvalidArgumentException(_("Desde configuración solo se puede dejar el tipo en centro n o centro sg"));
        }
        $planContable = trim((string) ($datos['plan_contable'] ?? ''));
        if ($planFijo && $centroActivo !== null) {
            $planContable = $centroActivo->planContableCodigo;
        } elseif ($planContable === '') {
            try {
                $centroPlan = $this->centros->porId($this->ambito->ejecutar()->centroId);
                $planContable = $centroPlan?->planContableCodigo ?? CatalogoPlanesContables::H16N;
            } catch (\Throwable) {
                $planContable = CatalogoPlanesContables::H16N;
            }
        }
        if ($this->planes->idPorCodigo($planContable) === null) {
            throw new InvalidArgumentException(_("Plan contable no válido"));
        }
        $ini = $this->fecha((string) ($datos['fecha_inicio'] ?? $actual->fechaInicio->format('Y-m-d')));
        $cie = $this->fecha((string) ($datos['fecha_cierre'] ?? $actual->fechaCierre->format('Y-m-d')));
        $sigla = trim((string) ($datos['centro'] ?? ''));
        if ($planFijo) {
            if ($sigla === '') {
                throw new InvalidArgumentException(_("La sigla es obligatoria"));
            }
            $this->guardarSigla($centroActivo, $sigla);
            $sigla = $actual->centro;
        } elseif ($sigla === '') {
            $sigla = $actual->centro;
        }
        $cfg = new ConfiguracionCentro(
            $sigla,
            $anio,
            $modo,
            $ini,
            $cie,
            $tipoCierre,
            $actual->numResidentes,
            $actual->version,
        );
        $this->repo->guardar($cfg);
        $this->sincronizarCentroActivo($tipo, $tipoCierre, $planContable);

        return $cfg;
    }

    private function centroActivo(): ?Centro
    {
        try {
            return $this->centros->porId($this->ambito->ejecutar()->centroId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function guardarSigla(?Centro $centro, string $sigla): void
    {
        if ($centro === null || $centro->id === null || $sigla === $centro->codigo) {
            return;
        }
        $otro = $this->centros->porCodigo($sigla);
        if ($otro !== null && $otro->id !== $centro->id) {
            throw new InvalidArgumentException(_("Ya existe un centro con esa sigla"));
        }
        $this->centros->guardar(new Centro(
            $centro->id,
            $sigla,
            $centro->nombre,
            $centro->tipo,
            $centro->tipoCierre,
            $centro->planContableCodigo,
            $centro->activo,
        ));
    }

    private function sincronizarCentroActivo(string $tipo, string $tipoCierre, string $planContable): void
    {
        try {
            $centro = $this->centros->porId($this->ambito->ejecutar()->centroId);
        } catch (\Throwable) {
            return;
        }
        if ($centro === null || $centro->id === null) {
            return;
        }
        if ($centro->tipo === $tipo && $centro->tipoCierre === $tipoCierre && $centro->planContableCodigo === $planContable) {
            return;
        }
        $this->centros->guardar(new Centro(
            $centro->id,
            $centro->codigo,
            $centro->nombre,
            $tipo,
            $tipoCierre,
            $planContable,
            $centro->activo,
        ));
    }

    private function fecha(string $raw): DateTimeImmutable
    {
        $raw = trim($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return new DateTimeImmutable($raw);
        }
        $dt = DateTimeImmutable::createFromFormat('!d/m/Y', $raw);
        if ($dt === false) {
            throw new InvalidArgumentException(_("Fecha inválida"));
        }

        return $dt;
    }
}
