<?php

declare(strict_types=1);

namespace src\informes\application;

use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\arqueo\domain\contracts\ArqueoRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\informes\domain\contracts\Informe613MesRepository;
use src\cierre\domain\services\RepartoCierre;
use src\informes\domain\services\Calculadora613;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\plan\domain\services\CatalogoPlanesContables;
use src\plan\domain\services\Estructura613P;
use src\personas\domain\contracts\PersonaRepository;
use src\presupuestos\domain\contracts\PresupuestoRepository;
use src\shared\domain\value_objects\Dinero;

final class ObtenerResumen613
{
    public function __construct(
        private readonly ConfiguracionRepository $config,
        private readonly AsientoRepository $asientos,
        private readonly PresupuestoRepository $presupuesto,
        private readonly PersonaRepository $personas,
        private readonly ResolverAmbitoActual $ambito,
        private readonly PartidaLaboresRepository $partidasLabores,
        private readonly Informe613MesRepository $informes613Mes,
        private readonly ArqueoRepository $arqueos,
        private readonly CuentaFisicaRepository $fisicas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(string $cuenta): array
    {
        $cuenta = strtoupper($cuenta);
        $cfg = $this->config->get();
        $periodo = $cfg->periodo();
        $contexto = $this->ambito->ejecutar();

        $desde = $periodo->fechaInicio->format('Y-m-d');
        $hasta = $periodo->fechaCorte->format('Y-m-d');

        $realizado = $this->asientos->realizadoPorConcepto(
            $contexto->centroId,
            $contexto->ejercicioId,
            $cuenta,
            $desde,
            $hasta,
        );

        $saldoCodigo9 = 0;
        if ($cuenta === 'P') {
            $saldos = $this->asientos->saldosPorCuenta(
                $contexto->centroId,
                $contexto->ejercicioId,
                $periodo->fechaInicio->format('Y-m-d'),
                $hasta,
                'P',
            );
            foreach ($saldos as $row) {
                if ($row['tipo'] === 'personal' && $row['codigo_maestro'] === '9' && str_starts_with($row['codigo'], 'CC.')) {
                    $saldoCodigo9 += $row['saldo_cents'];
                }
            }
        }

        $presu = $this->presupuesto->listar($cuenta);
        $partidasLabores = [];
        $codigosLabores = [];
        if ($cuenta === 'P') {
            $partidasLabores = $this->partidasLabores->paraCentro($contexto->centroId);
            $codigosLabores = Estructura613P::codigosLabores($partidasLabores);
            $defs = Calculadora613::estructuraP($partidasLabores);
        } else {
            $defs = Calculadora613::estructuraG();
        }
        $lineas = Calculadora613::lineas($realizado, $presu, $periodo, $defs, $saldoCodigo9);

        $index = [];
        foreach ($lineas as $l) {
            $index[$l['codigo']] = $l;
        }
        $sum = static function (array $codigos) use ($index): array {
            $prev = Dinero::zero();
            $real = Dinero::zero();
            foreach ($codigos as $c) {
                if (!isset($index[$c])) {
                    continue;
                }
                $prev = $prev->add(new Dinero($index[$c]['previsto']));
                $real = $real->add(new Dinero($index[$c]['realizado']));
            }
            $pct = $prev->isZero() ? null : (float) $real->toString() / (float) $prev->toString();

            return [
                'previsto' => $prev->toString(),
                'previsto_es' => $prev->formatEs(),
                'realizado' => $real->toString(),
                'realizado_es' => $real->formatEs(),
                'pct' => $pct,
            ];
        };

        $saldosTes = $this->asientos->saldosPorCuenta(
            $contexto->centroId,
            $contexto->ejercicioId,
            $desde,
            $hasta,
        );
        $caja = Dinero::zero();
        $banco = Dinero::zero();
        foreach ($saldosTes as $row) {
            if ($row['tipo'] !== 'tesoreria' || $row['libro'] === 'X') {
                continue;
            }
            $saldo = Dinero::fromCents($row['saldo_cents']);
            if ($row['codigo_maestro'] === 'CAJA') {
                $caja = $caja->add($saldo);
            }
            if ($row['codigo_maestro'] === 'BANCO') {
                $banco = $banco->add($saldo);
            }
        }

        $payload = [
            'cuenta' => $cuenta,
            'config' => $cfg->toArray(),
            'lineas' => $lineas,
        ];
        if ($cuenta === 'P') {
            $ingresos = $sum(['111', '112', '113', '12']);
            $gastos = $sum(['21', '212', '22', '23', '24', '25', '26', '27', '28']);
            $atLab = $sum(['51', '52']);
            $lab = $sum($codigosLabores);
            $dispPrev = (new Dinero($ingresos['previsto']))->sub(new Dinero($gastos['previsto']));
            $dispReal = (new Dinero($ingresos['realizado']))->sub(new Dinero($gastos['realizado']));
            $ay = $index['4'] ?? $sum(['4']);
            $nec = $index['6'] ?? $sum(['6']);
            $saldoPrev = $dispPrev->sub(new Dinero($ay['previsto']))->sub(new Dinero($atLab['previsto']))->sub(new Dinero($nec['previsto']))->sub(new Dinero($lab['previsto']));
            $saldoReal = $dispReal->sub(new Dinero($ay['realizado']))->sub(new Dinero($atLab['realizado']))->sub(new Dinero($nec['realizado']))->sub(new Dinero($lab['realizado']));
            $payload['totales'] = [
                'ingresos' => $ingresos,
                'gastos' => $gastos,
                'disponible' => [
                    'previsto' => $dispPrev->toString(),
                    'previsto_es' => $dispPrev->formatEs(),
                    'realizado' => $dispReal->toString(),
                    'realizado_es' => $dispReal->formatEs(),
                ],
                'atencion_labores' => $atLab,
                'labores' => $lab,
                'saldo_final' => [
                    'previsto' => $saldoPrev->toString(),
                    'previsto_es' => $saldoPrev->formatEs(),
                    'realizado' => $saldoReal->toString(),
                    'realizado_es' => $saldoReal->formatEs(),
                ],
            ];
            $this->aplicarCamposManuales($payload, $contexto->ejercicioId, $cfg->fechaCierre, 'P');
            $payload['plan_contable'] = CatalogoPlanesContables::H16N;
            $payload['partidas_labores'] = $codigosLabores;
        } else {
            $ingresos = $sum(['11', '12', '13', '14', '15']);
            $gastos = $sum(['201', '202', '203', '204', '205', '206', '207', '208', '209', '210', '211', '212', '213', '214', '215']);
            $ini = $index['32'] ?? $sum(['32']);
            $saldoIngGastosPrev = (new Dinero($ingresos['previsto']))->sub(new Dinero($gastos['previsto']));
            $saldoIngGastosReal = (new Dinero($ingresos['realizado']))->sub(new Dinero($gastos['realizado']));
            $dispPrev = $saldoIngGastosPrev->add(new Dinero($ini['previsto']));
            $dispReal = $saldoIngGastosReal->add(new Dinero($ini['realizado']));
            $mesCierre = (int) $cfg->fechaCierre->format('n');
            $n = count(RepartoCierre::inicialesEnReparto(
                $this->personas->listarDeCentro($contexto->centroId),
                $mesCierre,
            ));
            $meses = max(1, $periodo->mesesTranscurridos());
            $gastoViv = $n > 0 ? (new Dinero($gastos['realizado']))->divInt($n * $meses) : Dinero::zero();
            $payload['totales'] = [
                'ingresos' => $ingresos,
                'gastos' => $gastos,
                'saldo_ingresos_gastos' => [
                    'previsto' => $saldoIngGastosPrev->toString(),
                    'previsto_es' => $saldoIngGastosPrev->formatEs(),
                    'realizado' => $saldoIngGastosReal->toString(),
                    'realizado_es' => $saldoIngGastosReal->formatEs(),
                ],
                'disponible' => [
                    'previsto' => $dispPrev->toString(),
                    'previsto_es' => $dispPrev->formatEs(),
                    'realizado' => $dispReal->toString(),
                    'realizado_es' => $dispReal->formatEs(),
                ],
            ];
            $payload['num_personas'] = $n;
            $payload['gasto_vivienda_persona_mes'] = $gastoViv->toString();
            $payload['gasto_vivienda_persona_mes_es'] = $gastoViv->formatEs();
            $payload['saldo_caja'] = $caja->toString();
            $payload['saldo_caja_es'] = $caja->formatEs();
            $payload['saldo_banco'] = $banco->toString();
            $payload['saldo_banco_es'] = $banco->formatEs();
            $this->aplicarCamposManuales($payload, $contexto->ejercicioId, $cfg->fechaCierre, 'G');
            $this->aplicarArqueoCaja($payload, $contexto, $cfg->fechaCierre, $caja);
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function aplicarCamposManuales(
        array &$payload,
        int $ejercicioId,
        \DateTimeImmutable $fechaCierre,
        string $cuenta,
    ): void {
        $manual = $this->informes613Mes->buscar($ejercicioId, $fechaCierre, $cuenta);
        $payload['observaciones'] = $manual?->observaciones;
        $payload['enviado'] = $manual?->enviado ?? false;
        if ($cuenta === 'P') {
            $payload['saldo_cc_personales'] = $manual?->saldoCcPersonales;
        } else {
            $payload['media_cocina_mes'] = $manual?->mediaCocinaMes;
            $payload['media_cocina_acum'] = $manual?->mediaCocinaAcum;
            $payload['dinero_arqueo_caja'] = $manual?->dineroArqueoCaja;
            $payload['dinero_arqueo_banco'] = $manual?->dineroArqueoBanco;
        }
    }

    /** @param array<string, mixed> $payload */
    private function aplicarArqueoCaja(
        array &$payload,
        \src\ambito\domain\value_objects\ContextoActual $contexto,
        \DateTimeImmutable $fechaCierre,
        Dinero $saldoCaja,
    ): void {
        $cajas = $this->fisicas->listarActivasDeCentro($contexto->centroId, 'caja');
        $cajaFisicaId = $cajas[0]->id ?? null;
        $arqueo = $this->arqueos->ultimoCajaEnCierre($contexto->ejercicioId, $fechaCierre, $cajaFisicaId);
        if ($arqueo === null) {
            return;
        }
        $payload['arqueo_total'] = $arqueo->total->toString();
        $payload['arqueo_total_es'] = $arqueo->total->formatEs();
        $dif = $arqueo->total->sub($saldoCaja);
        $payload['arqueo_diferencia'] = $dif->toString();
        $payload['arqueo_diferencia_es'] = $dif->formatEs();
        $manual = trim((string) ($payload['dinero_arqueo_caja'] ?? ''));
        if ($manual === '') {
            $payload['dinero_arqueo_caja'] = $arqueo->total->formatEs();
        }
    }
}
