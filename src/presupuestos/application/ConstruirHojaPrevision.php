<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use DateTimeImmutable;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;
use src\asientos\domain\contracts\AsientoRepository;
use src\informes\domain\services\Calculadora613;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\presupuestos\domain\contracts\PrevisionPersonalRepository;
use src\presupuestos\domain\services\CalculadoraHojaPrevision;
use src\presupuestos\domain\services\SiguientePeriodoEjercicio;
use src\shared\domain\value_objects\PeriodoEjercicio;

/**
 * Carga acumulados, ejercicio anterior y previsto guardado, y arma la hoja 613 P.
 */
final class ConstruirHojaPrevision
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsientoRepository $asientos,
        private readonly PartidaLaboresRepository $partidasLabores,
        private readonly PrevisionPersonalRepository $prevision,
    ) {
    }

    /** @return array<string, mixed> */
    public function opcionesPrevision(): array
    {
        $ctx = $this->contextoEstructura();
        $trabajo = $ctx['ejercicio'];
        $defecto = $this->etiquetaPresupuestoDefectoDe($ctx['centro_id'], $trabajo);

        return [
            'etiquetas' => $this->etiquetasPrevision($ctx['centro_id'], $trabajo),
            'etiqueta_defecto' => $defecto,
            'etiqueta_trabajo' => $trabajo->etiqueta,
            'etiqueta_presupuesto' => $defecto,
        ];
    }

    /**
     * Crea el ejercicio del período siguiente en estado planificado si aún no existe,
     * para poder guardar la previsión sin cerrar el ejercicio abierto.
     */
    public function asegurarEjercicioPrevision(string $etiqueta): Ejercicio
    {
        $ctx = $this->contextoEstructura();
        $etiqueta = trim($etiqueta);
        $existente = $this->ejercicioPorEtiquetaEn($ctx['centro_id'], $etiqueta);
        if ($existente !== null) {
            return $existente;
        }
        $trabajo = $ctx['ejercicio'];
        $siguiente = SiguientePeriodoEjercicio::calcular($trabajo);
        if ($etiqueta !== $siguiente['etiqueta']) {
            throw new \InvalidArgumentException(_("No hay ejercicio para el año elegido"));
        }
        if ($trabajo->id === null) {
            throw new \InvalidArgumentException(_("No hay ejercicio para el año elegido"));
        }

        return $this->ejercicios->guardar(new Ejercicio(
            null,
            $ctx['centro_id'],
            $siguiente['etiqueta'],
            $siguiente['fecha_inicio'],
            $siguiente['fecha_fin'],
            $siguiente['fecha_inicio'],
            'planificado',
            $trabajo->id,
        ));
    }

    public function ejercicioPorEtiqueta(string $etiqueta): ?Ejercicio
    {
        $centroId = $this->contextoEstructura()['centro_id'];

        return $this->ejercicioPorEtiquetaEn($centroId, trim($etiqueta));
    }

    public function etiquetaPresupuestoPorDefecto(): string
    {
        $ctx = $this->contextoEstructura();

        return $this->etiquetaPresupuestoDefectoDe($ctx['centro_id'], $ctx['ejercicio']);
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $personaId, ?string $etiquetaObjetivo = null): array
    {
        $datos = $this->datosCentro();
        $ejercicioTrabajo = $datos['ejercicio'];
        $defecto = $this->etiquetaPresupuestoDefectoDe($datos['centro_id'], $ejercicioTrabajo);
        $etiqueta = trim((string) ($etiquetaObjetivo ?? ''));
        if ($etiqueta === '') {
            $etiqueta = $defecto;
        }
        $permitidas = $this->etiquetasPrevision($datos['centro_id'], $ejercicioTrabajo);
        if (!in_array($etiqueta, $permitidas, true)) {
            throw new \InvalidArgumentException(_("No hay ejercicio para el año elegido"));
        }
        $objetivo = $this->ejercicioPorEtiquetaEn($datos['centro_id'], $etiqueta);
        $guardadoObjetivo = $objetivo !== null && $objetivo->id !== null
            ? self::mapaGuardado($this->prevision->listarDePersona((int) $objetivo->id, $personaId))
            : [];
        $guardadoActual = self::mapaGuardado($this->prevision->listarDePersona((int) $ejercicioTrabajo->id, $personaId));

        $lineas = CalculadoraHojaPrevision::lineas(
            $datos['estructura'],
            $datos['acumulado'][$personaId] ?? [],
            $datos['anterior_total'][$personaId] ?? [],
            $datos['anterior_periodo'][$personaId] ?? [],
            $guardadoObjetivo,
            $datos['saldos_cc'][$personaId] ?? 0,
            $datos['periodo']->mesesTranscurridos(),
            $datos['periodo']->mesesTotales(),
        );
        foreach ($lineas as $i => $l) {
            $codigo = (string) $l['codigo'];
            $prevActual = $guardadoActual[$codigo] ?? null;
            $lineas[$i]['previsto_ejercicio_actual_cents'] = $prevActual;
            $lineas[$i]['previsto_ejercicio_actual_es'] = $prevActual !== null
                ? \src\shared\domain\value_objects\Dinero::fromCents($prevActual)->formatEs()
                : null;
        }

        return [
            'ejercicio_id' => $objetivo?->id,
            'ejercicio_trabajo_id' => $ejercicioTrabajo->id,
            'etiqueta_presupuesto' => $etiqueta,
            'etiqueta_trabajo' => $ejercicioTrabajo->etiqueta,
            'etiqueta_defecto' => $defecto,
            'etiquetas' => $permitidas,
            'anio_presupuesto' => $etiqueta,
            'anio_trabajo' => $ejercicioTrabajo->etiqueta,
            'anios_disponibles' => $permitidas,
            'periodo' => [
                'fecha_inicio' => $ejercicioTrabajo->fechaInicio->format('Y-m-d'),
                'fecha_fin' => $ejercicioTrabajo->fechaFin->format('Y-m-d'),
                'fecha_corte' => $ejercicioTrabajo->fechaCorte->format('Y-m-d'),
                'meses_transcurridos' => $datos['periodo']->mesesTranscurridos(),
                'meses_totales' => $datos['periodo']->mesesTotales(),
            ],
            'tiene_ejercicio_anterior' => $datos['anterior'] !== null,
            'lineas' => $lineas,
        ];
    }

    /** @return list<string> */
    private function etiquetasPrevision(int $centroId, Ejercicio $trabajo): array
    {
        $etiquetas = [];
        foreach ($this->ejercicios->listarDeCentro($centroId) as $ej) {
            $etiquetas[] = $ej->etiqueta;
        }
        $defecto = $this->etiquetaPresupuestoDefectoDe($centroId, $trabajo);
        if (!in_array($defecto, $etiquetas, true)) {
            $etiquetas[] = $defecto;
        }

        return $etiquetas;
    }

    private function etiquetaPresupuestoDefectoDe(int $centroId, Ejercicio $trabajo): string
    {
        if ($trabajo->id !== null) {
            $enlazado = $this->ejercicios->posteriorConAnteriorId($trabajo->id);
            if ($enlazado !== null) {
                return $enlazado->etiqueta;
            }
        }
        $siguiente = SiguientePeriodoEjercicio::calcular($trabajo);
        $porFecha = $this->ejercicioPorInicio($centroId, $siguiente['fecha_inicio']);
        if ($porFecha !== null) {
            return $porFecha->etiqueta;
        }

        return $siguiente['etiqueta'];
    }

    private function ejercicioPorEtiquetaEn(int $centroId, string $etiqueta): ?Ejercicio
    {
        if ($etiqueta === '') {
            return null;
        }
        foreach ($this->ejercicios->listarDeCentro($centroId) as $ej) {
            if ($ej->etiqueta === $etiqueta) {
                return $ej;
            }
        }

        return null;
    }

    private function ejercicioPorInicio(int $centroId, \DateTimeImmutable $inicio): ?Ejercicio
    {
        $clave = $inicio->format('Y-m-d');
        foreach ($this->ejercicios->listarDeCentro($centroId) as $ej) {
            if ($ej->fechaInicio->format('Y-m-d') === $clave) {
                return $ej;
            }
        }

        return null;
    }

    /** @param list<\src\presupuestos\domain\entity\LineaPrevisionPersonal> $lineas */
    private static function mapaGuardado(array $lineas): array
    {
        $map = [];
        foreach ($lineas as $l) {
            $map[$l->conceptoCodigo] = $l->previstoCents;
        }

        return $map;
    }

    /**
     * @return array{
     *   centro_id: int,
     *   ejercicio: Ejercicio,
     *   periodo: PeriodoEjercicio,
     *   estructura: list<array{codigo:string,etiqueta:string,codigos:list<string>,grupo?:string}>
     * }
     */
    public function contextoEstructura(): array
    {
        $ctx = $this->ambito->ejecutar();
        $ejercicio = $this->ejercicios->porId($ctx->ejercicioId);
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new \RuntimeException(_("El centro no tiene ningún ejercicio abierto."));
        }

        return [
            'centro_id' => $ctx->centroId,
            'ejercicio' => $ejercicio,
            'periodo' => $ejercicio->periodo(),
            'estructura' => Calculadora613::estructuraP($this->partidasLabores->paraCentro($ctx->centroId)),
        ];
    }

    /** Año o etiqueta del ejercicio presupuestado (el siguiente al de trabajo). */
    public function anioPresupuesto(): string
    {
        return $this->etiquetaPresupuestoPorDefecto();
    }

    public static function anioDeEjercicio(Ejercicio $ejercicio): int
    {
        if (ctype_digit($ejercicio->etiqueta)) {
            return (int) $ejercicio->etiqueta;
        }

        return (int) $ejercicio->fechaInicio->format('Y');
    }

    /**
     * @return array{
     *   centro_id: int,
     *   ejercicio: Ejercicio,
     *   anterior: ?Ejercicio,
     *   periodo: PeriodoEjercicio,
     *   estructura: list<array{codigo:string,etiqueta:string,codigos:list<string>,grupo?:string}>,
     *   acumulado: array<int, array<string, int>>,
     *   anterior_total: array<int, array<string, int>>,
     *   anterior_periodo: array<int, array<string, int>>,
     *   saldos_cc: array<int, int>
     * }
     */
    public function datosCentro(): array
    {
        $base = $this->contextoEstructura();
        $ejercicio = $base['ejercicio'];
        $periodo = $base['periodo'];
        $desde = $ejercicio->fechaInicio->format('Y-m-d');
        $hasta = $ejercicio->fechaCorte->format('Y-m-d');
        $ejercicioId = (int) $ejercicio->id;
        $acumulado = $this->asientos->realizadoPorConceptoYPersona(
            $base['centro_id'],
            $ejercicioId,
            'P',
            $desde,
            $hasta,
        );
        $anterior = $ejercicio->ejercicioAnteriorId !== null
            ? $this->ejercicios->porId($ejercicio->ejercicioAnteriorId)
            : null;
        $anteriorTotal = [];
        $anteriorPeriodo = [];
        if ($anterior !== null && $anterior->id !== null) {
            $antDesde = $anterior->fechaInicio->format('Y-m-d');
            $antFin = $anterior->fechaFin->format('Y-m-d');
            $anteriorTotal = $this->asientos->realizadoPorConceptoYPersona(
                $base['centro_id'],
                $anterior->id,
                'P',
                $antDesde,
                $antFin,
            );
            $hastaPeriodo = self::hastaTrasMeses(
                $anterior->fechaInicio,
                $periodo->mesesTranscurridos(),
                $anterior->fechaFin,
            );
            $anteriorPeriodo = $this->asientos->realizadoPorConceptoYPersona(
                $base['centro_id'],
                $anterior->id,
                'P',
                $antDesde,
                $hastaPeriodo->format('Y-m-d'),
            );
        }
        $saldosCc = [];
        foreach ($this->asientos->saldosPorCuenta(
            $base['centro_id'],
            $ejercicioId,
            $desde,
            $hasta,
            'P',
        ) as $row) {
            if ($row['tipo'] === 'personal' && $row['codigo_maestro'] === '9' && $row['persona_id'] !== null) {
                $saldosCc[$row['persona_id']] = ($saldosCc[$row['persona_id']] ?? 0) + $row['saldo_cents'];
            }
        }

        return [
            'centro_id' => $base['centro_id'],
            'ejercicio' => $ejercicio,
            'anterior' => $anterior,
            'periodo' => $periodo,
            'estructura' => $base['estructura'],
            'acumulado' => $acumulado,
            'anterior_total' => $anteriorTotal,
            'anterior_periodo' => $anteriorPeriodo,
            'saldos_cc' => $saldosCc,
        ];
    }

    public static function hastaTrasMeses(
        DateTimeImmutable $inicio,
        int $meses,
        DateTimeImmutable $tope,
    ): DateTimeImmutable {
        if ($meses <= 0) {
            return $inicio->modify('-1 day');
        }
        $hasta = $inicio->modify('+' . ($meses - 1) . ' months')->modify('last day of this month');
        if ($hasta > $tope) {
            return $tope;
        }

        return $hasta;
    }
}
