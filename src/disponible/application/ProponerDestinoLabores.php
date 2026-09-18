<?php

declare(strict_types=1);

namespace src\disponible\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\disponible\domain\contracts\SaldoDisponibleRepository;
use src\disponible\domain\contracts\TramosDesgravacionRepository;
use src\disponible\domain\services\RepartidorLabores;
use src\disponible\domain\services\TramosDesgravacion;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\personas\application\EstimarBasesLiquidables;
use src\personas\domain\contracts\PersonaRepository;
use src\presupuestos\domain\contracts\PresupuestoRepository;
use src\shared\domain\value_objects\Dinero;

final class ProponerDestinoLabores
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly SaldoDisponibleRepository $saldos,
        private readonly AsignacionLaboresRepository $asignaciones,
        private readonly TramosDesgravacionRepository $tramos,
        private readonly PartidaLaboresRepository $partidas,
        private readonly PresupuestoRepository $presupuesto,
        private readonly PersonaRepository $personas,
        private readonly EstimarBasesLiquidables $basesLiquidables,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $partidas = $this->partidas->paraCentro($ctx->centroId);
        if ($partidas === []) {
            throw new InvalidArgumentException(_("No hay partidas 7 en este centro"));
        }
        $realizado = [];
        $yaPersona = [];
        foreach ($this->asignaciones->realizadoLaboresPorPersona($ctx->ejercicioId) as $row) {
            $cod = $row['codigo_maestro'];
            $realizado[$cod] = ($realizado[$cod] ?? 0) + $row['cents'];
            $pid = $row['persona_id'];
            $yaPersona[$pid][$cod] = ($yaPersona[$pid][$cod] ?? 0) + $row['cents'];
        }
        $desgrava = [];
        $partidasIn = [];
        foreach ($partidas as $p) {
            $previsto = Dinero::fromInput($this->presupuesto->previsto('P', $p['codigo']))->toCents();
            $hecho = $realizado[$p['codigo']] ?? 0;
            $hueco = max(0, $previsto - $hecho);
            $desgrava[$p['codigo']] = (bool) $p['desgrava'];
            $partidasIn[] = [
                'codigo' => $p['codigo'],
                'etiqueta' => $p['etiqueta'],
                'desgrava' => $desgrava[$p['codigo']],
                'orden' => (int) $p['orden'],
                'hueco_cents' => $hueco,
                'previsto_cents' => $previsto,
                'realizado_cents' => $hecho,
            ];
        }

        $saldos = [];
        foreach ($this->saldos->listarDeCentro($ctx->centroId) as $s) {
            $saldos[$s['persona_id']] = $s['saldo_cents'];
        }
        $configTramos = $this->tramos->deCentro($ctx->centroId);
        $bases = $this->basesLiquidables->deCentro();
        $personasIn = [];
        $nombres = [];
        foreach ($this->personas->listarDeCentro($ctx->centroId) as $p) {
            if ($p->id === null) {
                continue;
            }
            $nombres[$p->id] = $p;
            $disp = $saldos[$p->id] ?? 0;
            if ($disp <= 0) {
                continue;
            }
            $ya = 0;
            foreach ($yaPersona[$p->id] ?? [] as $cod => $cents) {
                if ($desgrava[$cod] ?? false) {
                    $ya += $cents;
                }
            }
            $baseCents = $p->baseLiquidable?->toCents();
            if ($baseCents === null || $baseCents <= 0) {
                $baseCents = $bases[$p->id]['cents'] ?? null;
            }
            $personasIn[] = [
                'id' => $p->id,
                'disponible_cents' => $disp,
                'puede_desgravar' => $p->puedeDesgravar,
                'ya_desgravado_cents' => $ya,
                'tope_cents' => TramosDesgravacion::topeBaseCents(
                    $baseCents,
                    $configTramos['maximo_pct'],
                ),
            ];
        }

        $reparto = RepartidorLabores::repartir(
            $personasIn,
            $partidasIn,
            $configTramos['tramos'],
        );
        $lineasRepo = [];
        $porPersona = [];
        foreach ($reparto['lineas'] as $l) {
            $lineasRepo[] = [
                'persona_id' => $l['persona_id'],
                'codigo_maestro' => $l['codigo'],
                'importe_cents' => $l['cents'],
            ];
            $pid = $l['persona_id'];
            if (!isset($porPersona[$pid])) {
                $porPersona[$pid] = [
                    'persona_id' => $pid,
                    'iniciales' => $nombres[$pid]->iniciales,
                    'nombre' => $nombres[$pid]->nombreCompleto(),
                    'puede_desgravar' => $nombres[$pid]->puedeDesgravar,
                    'texto' => '',
                    'lineas' => [],
                ];
            }
            $d = Dinero::fromCents($l['cents']);
            $porPersona[$pid]['lineas'][] = [
                'codigo' => $l['codigo'],
                'etiqueta' => $l['etiqueta'],
                'cents' => $l['cents'],
                'importe_es' => $d->formatEs(),
            ];
        }
        foreach ($reparto['textos'] as $t) {
            if (isset($porPersona[$t['persona_id']])) {
                $porPersona[$t['persona_id']]['texto'] = $t['texto'];
            }
        }
        $id = null;
        if ($lineasRepo === []) {
            $this->asignaciones->borrarBorradores($ctx->centroId, $ctx->ejercicioId);
        } else {
            $id = $this->asignaciones->guardarBorrador($ctx->centroId, $ctx->ejercicioId, $lineasRepo);
        }

        return [
            'asignacion_id' => $id,
            'partidas' => array_map(static function (array $p): array {
                return [
                    'codigo' => $p['codigo'],
                    'etiqueta' => $p['etiqueta'],
                    'desgrava' => $p['desgrava'],
                    'previsto_es' => Dinero::fromCents($p['previsto_cents'])->formatEs(),
                    'realizado_es' => Dinero::fromCents($p['realizado_cents'])->formatEs(),
                    'hueco_es' => $p['hueco_cents'] === PHP_INT_MAX
                        ? '—'
                        : Dinero::fromCents($p['hueco_cents'])->formatEs(),
                ];
            }, $partidasIn),
            'personas' => array_values($porPersona),
        ];
    }
}
