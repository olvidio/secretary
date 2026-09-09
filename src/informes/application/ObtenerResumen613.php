<?php

declare(strict_types=1);

namespace src\informes\application;

use src\ambito\application\ResolverAmbitoActual;
use src\asientos\domain\contracts\AsientoRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\informes\domain\services\Calculadora613;
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
        $defs = $cuenta === 'P' ? Calculadora613::estructuraP() : Calculadora613::estructuraG();
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
            $gastos = $sum(['21', '22', '23', '24', '25', '26', '27', '28']);
            $atLab = $sum(['51', '52']);
            $lab = $sum(['71', '72', '73', '74', '75', '76', '77', '78', '79']);
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
            $payload['observaciones'] = $cfg->observaciones613P;
            $payload['saldo_cc_personales'] = $cfg->saldoCcPersonales;
        } else {
            $ingresos = $sum(['11', '12', '13', '14', '15']);
            $gastos = $sum(['201', '202', '203', '204', '205', '206', '207', '208', '209', '210', '211', '212', '213', '214', '215']);
            $ini = $index['32'] ?? $sum(['32']);
            $saldoIngGastosPrev = (new Dinero($ingresos['previsto']))->sub(new Dinero($gastos['previsto']));
            $saldoIngGastosReal = (new Dinero($ingresos['realizado']))->sub(new Dinero($gastos['realizado']));
            $dispPrev = $saldoIngGastosPrev->add(new Dinero($ini['previsto']));
            $dispReal = $saldoIngGastosReal->add(new Dinero($ini['realizado']));
            $n = $cfg->numResidentes;
            if ($n === null) {
                $n = 0;
                foreach ($this->personas->listarDeCentro($contexto->centroId) as $p) {
                    if (!$p->exentaEnMes((int) $cfg->fechaCierre->format('n'))) {
                        $n++;
                    }
                }
            }
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
            $payload['media_cocina_mes'] = $cfg->mediaCocinaMes;
            $payload['media_cocina_acum'] = $cfg->mediaCocinaAcum;
            $payload['saldo_caja'] = $caja->toString();
            $payload['saldo_caja_es'] = $caja->formatEs();
            $payload['saldo_banco'] = $banco->toString();
            $payload['saldo_banco_es'] = $banco->formatEs();
            $payload['observaciones'] = $cfg->observaciones613G;
        }

        return $payload;
    }
}
