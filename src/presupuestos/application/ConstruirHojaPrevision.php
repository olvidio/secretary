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
    public function ejecutar(int $personaId): array
    {
        $datos = $this->datosCentro();
        $guardado = [];
        foreach ($this->prevision->listarDePersona($datos['ejercicio']->id, $personaId) as $l) {
            $guardado[$l->conceptoCodigo] = $l->previstoCents;
        }
        $lineas = CalculadoraHojaPrevision::lineas(
            $datos['estructura'],
            $datos['acumulado'][$personaId] ?? [],
            $datos['anterior_total'][$personaId] ?? [],
            $datos['anterior_periodo'][$personaId] ?? [],
            $guardado,
            $datos['saldos_cc'][$personaId] ?? 0,
            $datos['periodo']->mesesTranscurridos(),
            $datos['periodo']->mesesTotales(),
        );

        return [
            'ejercicio_id' => $datos['ejercicio']->id,
            'periodo' => [
                'fecha_inicio' => $datos['ejercicio']->fechaInicio->format('Y-m-d'),
                'fecha_fin' => $datos['ejercicio']->fechaFin->format('Y-m-d'),
                'fecha_corte' => $datos['ejercicio']->fechaCorte->format('Y-m-d'),
                'meses_transcurridos' => $datos['periodo']->mesesTranscurridos(),
                'meses_totales' => $datos['periodo']->mesesTotales(),
            ],
            'tiene_ejercicio_anterior' => $datos['anterior'] !== null,
            'lineas' => $lineas,
        ];
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
