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
        $referencia = $this->ejercicioInmediatamenteAnterior($datos['centro_id'], $etiqueta);
        if ($referencia !== null && $referencia->id !== null) {
            $realizadoRef = $this->datosRealizado($datos['centro_id'], $referencia);
            $acumuladoPersona = $realizadoRef['acumulado'][$personaId] ?? [];
            $anteriorTotalPersona = $realizadoRef['anterior_total'][$personaId] ?? [];
            $anteriorPeriodoPersona = $realizadoRef['anterior_periodo'][$personaId] ?? [];
            $saldoCc = $realizadoRef['saldos_cc'][$personaId] ?? 0;
            $periodoRef = $realizadoRef['periodo'];
            $guardadoReferencia = self::mapaGuardado(
                $this->prevision->listarDePersona((int) $referencia->id, $personaId),
            );
            $tieneEjercicioAnterior = $realizadoRef['anterior'] !== null;
        } else {
            $acumuladoPersona = [];
            $anteriorTotalPersona = [];
            $anteriorPeriodoPersona = [];
            $saldoCc = 0;
            $periodoRef = $ejercicioTrabajo->periodo();
            $guardadoReferencia = [];
            $tieneEjercicioAnterior = false;
        }

        $lineas = CalculadoraHojaPrevision::lineas(
            $datos['estructura'],
            $acumuladoPersona,
            $anteriorTotalPersona,
            $anteriorPeriodoPersona,
            $guardadoObjetivo,
            $saldoCc,
            $referencia !== null ? $periodoRef->mesesTranscurridos() : 0,
            max(1, $periodoRef->mesesTotales()),
        );
        foreach ($lineas as $i => $l) {
            $codigo = (string) $l['codigo'];
            $prevRef = $guardadoReferencia[$codigo] ?? null;
            $lineas[$i]['previsto_ejercicio_actual_cents'] = $prevRef;
            $lineas[$i]['previsto_ejercicio_actual_es'] = $prevRef !== null
                ? \src\shared\domain\value_objects\Dinero::fromCents($prevRef)->formatEs()
                : null;
            if ($referencia === null) {
                $lineas[$i]['acumulado'] = null;
                $lineas[$i]['acumulado_es'] = null;
                $lineas[$i]['acumulado_cents'] = 0;
                $lineas[$i]['calculado'] = null;
                $lineas[$i]['calculado_es'] = null;
                $lineas[$i]['calculado_cents'] = 0;
            }
        }

        return [
            'ejercicio_id' => $objetivo?->id,
            'ejercicio_trabajo_id' => $ejercicioTrabajo->id,
            'etiqueta_referencia' => $referencia?->etiqueta,
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
            'tiene_ejercicio_anterior' => $tieneEjercicioAnterior,
            'lineas' => $lineas,
        ];
    }

    /** Ejercicio cuyo período precede al de la etiqueta de previsión elegida (p. ej. 2025 si se mira 2026). */
    public function ejercicioInmediatamenteAnterior(int $centroId, string $etiquetaObjetivo): ?Ejercicio
    {
        $etiquetaObjetivo = trim($etiquetaObjetivo);
        if ($etiquetaObjetivo === '') {
            return null;
        }
        $objetivo = $this->ejercicioPorEtiquetaEn($centroId, $etiquetaObjetivo);
        if ($objetivo?->ejercicioAnteriorId !== null) {
            return $this->ejercicios->porId($objetivo->ejercicioAnteriorId);
        }
        foreach ($this->ejercicios->listarDeCentro($centroId) as $ej) {
            $siguiente = SiguientePeriodoEjercicio::calcular($ej);
            if ($siguiente['etiqueta'] === $etiquetaObjetivo) {
                return $ej;
            }
        }

        return null;
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

    /** Ejercicio cuyas cifras de presupuesto alimentan el 613 frente al ejercicio abierto. */
    public function ejercicioIdPresupuestoInformes(): int
    {
        $ctx = $this->contextoEstructura();
        $trabajo = $ctx['ejercicio'];
        $etiqueta = $this->etiquetaPresupuestoDefectoDe($ctx['centro_id'], $trabajo);
        $objetivo = $this->ejercicioPorEtiquetaEn($ctx['centro_id'], $etiqueta);
        if ($objetivo?->id !== null && $etiqueta !== $trabajo->etiqueta) {
            return (int) $objetivo->id;
        }

        return (int) $trabajo->id;
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
        $realizado = $this->datosRealizado($base['centro_id'], $base['ejercicio']);

        return [
            'centro_id' => $base['centro_id'],
            'ejercicio' => $base['ejercicio'],
            'anterior' => $realizado['anterior'],
            'periodo' => $realizado['periodo'],
            'estructura' => $base['estructura'],
            'acumulado' => $realizado['acumulado'],
            'anterior_total' => $realizado['anterior_total'],
            'anterior_periodo' => $realizado['anterior_periodo'],
            'saldos_cc' => $realizado['saldos_cc'],
        ];
    }

    /**
     * Realizado y saldos de un ejercicio concreto (y su anterior contable).
     *
     * @return array{
     *   acumulado: array<int, array<string, int>>,
     *   anterior_total: array<int, array<string, int>>,
     *   anterior_periodo: array<int, array<string, int>>,
     *   saldos_cc: array<int, int>,
     *   periodo: PeriodoEjercicio,
     *   anterior: ?Ejercicio
     * }
     */
    private function datosRealizado(int $centroId, Ejercicio $ejercicio): array
    {
        $periodo = $ejercicio->periodo();
        $desde = $ejercicio->fechaInicio->format('Y-m-d');
        $hasta = $ejercicio->fechaCorte->format('Y-m-d');
        $ejercicioId = (int) $ejercicio->id;
        $acumulado = $this->asientos->realizadoPorConceptoYPersona(
            $centroId,
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
                $centroId,
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
                $centroId,
                $anterior->id,
                'P',
                $antDesde,
                $hastaPeriodo->format('Y-m-d'),
            );
        }
        $saldosCc = [];
        foreach ($this->asientos->saldosPorCuenta(
            $centroId,
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
            'acumulado' => $acumulado,
            'anterior_total' => $anteriorTotal,
            'anterior_periodo' => $anteriorPeriodo,
            'saldos_cc' => $saldosCc,
            'periodo' => $periodo,
            'anterior' => $anterior,
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
