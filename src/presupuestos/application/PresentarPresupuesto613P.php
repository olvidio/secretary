<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use src\ambito\application\ResolverAmbitoActual;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\informes\domain\services\Calculadora613;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\plan\domain\services\Estructura613P;
use src\shared\domain\value_objects\Dinero;

/** Vista anual del presupuesto P con la misma estructura de filas que el 613 P. */
final class PresentarPresupuesto613P
{
    public function __construct(
        private readonly GuardarPresupuesto $presupuesto,
        private readonly ResolverAmbitoActual $ambito,
        private readonly PartidaLaboresRepository $partidasLabores,
        private readonly ConfiguracionRepository $config,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(?int $ejercicioId, string $etiquetaEjercicio): array
    {
        $contexto = $this->ambito->ejecutar();
        $partidas = $this->partidasLabores->paraCentro($contexto->centroId);
        $defs = Calculadora613::estructuraP($partidas);
        $codigosLabores = Estructura613P::codigosLabores($partidas);

        $prevIndex = [];
        foreach ($this->presupuesto->listarPorEjercicio('P', $ejercicioId) as $row) {
            $prevIndex[(string) $row['concepto_codigo']] = Dinero::fromInput((string) ($row['previsto'] ?? '0'));
        }

        $lineas = [];
        foreach ($defs as $def) {
            $previsto = Dinero::zero();
            foreach ($def['codigos'] as $cod) {
                $previsto = $previsto->add($prevIndex[$cod] ?? Dinero::zero());
            }
            $lineas[] = [
                'codigo' => $def['codigo'],
                'etiqueta' => $def['etiqueta'],
                'previsto' => $previsto->toString(),
                'previsto_es' => $previsto->formatEs(),
                'realizado' => '0.00',
                'realizado_es' => '0,00',
                'pct' => null,
            ];
        }

        $index = [];
        foreach ($lineas as $l) {
            $index[$l['codigo']] = $l;
        }

        $sum = static function (array $codigos) use ($index): array {
            $prev = Dinero::zero();
            foreach ($codigos as $c) {
                if (!isset($index[$c])) {
                    continue;
                }
                $prev = $prev->add(new Dinero($index[$c]['previsto']));
            }

            return [
                'previsto' => $prev->toString(),
                'previsto_es' => $prev->formatEs(),
                'realizado' => '0.00',
                'realizado_es' => '0,00',
                'pct' => null,
            ];
        };

        $ingresos = $sum(['111', '112', '113', '12']);
        $gastos = $sum(['211', '212', '22', '23', '24', '25', '26', '27', '28']);
        $atLab = $sum(['51', '52']);
        $lab = $sum($codigosLabores);
        $dispPrev = (new Dinero($ingresos['previsto']))->sub(new Dinero($gastos['previsto']));
        $ay = $index['4'] ?? $sum(['4']);
        $nec = $index['6'] ?? $sum(['6']);
        $saldoPrev = $dispPrev
            ->sub(new Dinero($ay['previsto']))
            ->sub(new Dinero($atLab['previsto']))
            ->sub(new Dinero($nec['previsto']))
            ->sub(new Dinero($lab['previsto']));

        $cfg = $this->config->get();

        return [
            'centro' => $cfg->centro,
            'etiqueta_ejercicio' => $etiquetaEjercicio,
            'lineas' => $lineas,
            'partidas_labores' => $codigosLabores,
            'totales' => [
                'ingresos' => $ingresos,
                'gastos' => $gastos,
                'disponible' => [
                    'previsto' => $dispPrev->toString(),
                    'previsto_es' => $dispPrev->formatEs(),
                    'realizado' => '0.00',
                    'realizado_es' => '0,00',
                ],
                'atencion_labores' => $atLab,
                'labores' => $lab,
                'saldo_final' => [
                    'previsto' => $saldoPrev->toString(),
                    'previsto_es' => $saldoPrev->formatEs(),
                    'realizado' => '0.00',
                    'realizado_es' => '0,00',
                ],
            ],
            'valores' => array_map(
                static fn (Dinero $d): string => $d->formatEs(),
                $prevIndex,
            ),
        ];
    }
}
