<?php

declare(strict_types=1);

namespace src\apuntes\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\EntradaPeriodicaRepository;
use src\apuntes\domain\contracts\PlantillaApunteRepository;
use src\apuntes\domain\entity\EntradaPeriodica;
use src\apuntes\domain\value_objects\PeriodicidadEntrada;
use src\apuntes\domain\value_objects\ReferenciaPlantillaEnConcepto;
use src\conceptos\application\ResolverConceptosCentro;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class GuardarEntradaPeriodica
{
    public function __construct(
        private readonly EntradaPeriodicaRepository $entradas,
        private readonly ResolverAmbitoActual $ambito,
        private readonly ComprobarAccesoCentroSg $centroSg,
        private readonly ResolverConceptosCentro $conceptos,
        private readonly PersonaRepository $personas,
        private readonly PlantillaApunteRepository $plantillas,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function ejecutar(array $datos): array
    {
        $this->centroSg->ejecutar();
        $ctx = $this->ambito->ejecutar();
        $iniciales = trim((string) ($datos['iniciales'] ?? ''));
        if ($iniciales !== '' && $this->personas->porInicialesDeCentro($ctx->centroId, $iniciales) === null) {
            throw new InvalidArgumentException(_("Iniciales no reconocidas en este centro"));
        }
        $concepto = trim((string) ($datos['concepto_codigo'] ?? ''));
        if ($concepto === '') {
            throw new InvalidArgumentException(_("Concepto no válido"));
        }
        $plantillaId = ReferenciaPlantillaEnConcepto::idDesdeCodigo($concepto);
        if ($plantillaId !== null) {
            $plantilla = $this->plantillas->porId($ctx->centroId, $plantillaId);
            if ($plantilla === null || strtoupper($plantilla->cuenta) !== 'G' || !$plantilla->activa) {
                throw new InvalidArgumentException(_("Plantilla no válida"));
            }
        } elseif ($this->conceptos->buscar($ctx->centroId, 'G', $concepto) === null) {
            throw new InvalidArgumentException(_("Concepto no válido"));
        }
        $obsRaw = trim((string) ($datos['observaciones'] ?? ''));
        $cantidad = Dinero::fromInput((string) ($datos['cantidad'] ?? ''));
        if ($cantidad->isNegative() || $cantidad->isZero()) {
            throw new InvalidArgumentException(_("La cantidad debe ser positiva"));
        }
        $periodicidad = PeriodicidadEntrada::fromString((string) ($datos['periodicidad'] ?? ''));
        $fechaRaw = trim((string) ($datos['fecha_ancla'] ?? $datos['fecha'] ?? ''));
        if ($fechaRaw === '') {
            throw new InvalidArgumentException(_("Indique la fecha de referencia"));
        }
        $fechaAncla = DateTimeImmutable::createFromFormat('Y-m-d', $fechaRaw);
        if ($fechaAncla === false) {
            throw new InvalidArgumentException(_("Fecha no válida"));
        }
        $id = self::normalizarId($datos['id'] ?? null);
        if ($id !== null && $this->entradas->porId($ctx->centroId, $id) === null) {
            throw new InvalidArgumentException(_("Entrada periódica no encontrada"));
        }

        $guardada = $this->entradas->guardar(new EntradaPeriodica(
            $id,
            $ctx->centroId,
            $iniciales,
            $concepto,
            $obsRaw === '' ? null : $obsRaw,
            $cantidad,
            $periodicidad,
            $fechaAncla,
            true,
        ));

        return $guardada->toArray();
    }

    private static function normalizarId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }
        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }
}
