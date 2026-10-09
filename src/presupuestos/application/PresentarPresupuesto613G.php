<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\informes\domain\services\Calculadora613;
use src\plan\domain\contracts\DestinoSgRepository;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\domain\value_objects\Dinero;

/** Vista anual del presupuesto G (613 G o 613 G-D en centro sg). */
final class PresentarPresupuesto613G
{
    public function __construct(
        private readonly GuardarPresupuesto $presupuesto,
        private readonly ResolverAmbitoActual $ambito,
        private readonly ConfiguracionRepository $config,
        private readonly CentroRepository $centros,
        private readonly ?DestinoSgRepository $destinosSg = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(?int $ejercicioId, string $etiquetaEjercicio): array
    {
        $contexto = $this->ambito->ejecutar();
        $centro = $this->centros->porId($contexto->centroId);
        $esCentroSg = $centro !== null && CatalogoPlanesContables::esCentroSg($centro->planContableCodigo);

        $defs = $esCentroSg
            ? Calculadora613::estructuraCentroSg($this->destinosNombrados($contexto->centroId))
            : Calculadora613::estructuraG();

        $prevIndex = [];
        foreach ($this->presupuesto->listarPorEjercicio('G', $ejercicioId) as $row) {
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

        $cfg = $this->config->get();
        $centroEtiqueta = $esCentroSg && $centro !== null ? $centro->codigo : $cfg->centro;

        if ($esCentroSg) {
            $ingresos = $sum(['11', '12', '13', '14']);
            $gastos = $sum(['21', '22', '23', '24', '25', '26', '27', '28']);
            $ini = $index['32'] ?? $sum(['32']);
            $destinosCodigos = [];
            foreach ($defs as $d) {
                $n = (int) $d['codigo'];
                if ($n >= 41 && $n <= 54) {
                    $destinosCodigos[] = (string) $d['codigo'];
                }
            }
            $destinos = $sum($destinosCodigos);
            $saldoIngGastosPrev = (new Dinero($ingresos['previsto']))->sub(new Dinero($gastos['previsto']));
            $dispPrev = $saldoIngGastosPrev->add(new Dinero($ini['previsto']));
            $saldoPrev = $dispPrev->sub(new Dinero($destinos['previsto']));

            return [
                'centro' => $centroEtiqueta,
                'etiqueta_ejercicio' => $etiquetaEjercicio,
                'plan_contable' => CatalogoPlanesContables::CENTRO_SG,
                'lineas' => $lineas,
                'totales' => [
                    'ingresos' => $ingresos,
                    'gastos' => $gastos,
                    'destinos' => $destinos,
                    'saldo_ingresos_gastos' => [
                        'previsto' => $saldoIngGastosPrev->toString(),
                        'previsto_es' => $saldoIngGastosPrev->formatEs(),
                        'realizado' => '0.00',
                        'realizado_es' => '0,00',
                        'pct' => null,
                    ],
                    'disponible' => [
                        'previsto' => $dispPrev->toString(),
                        'previsto_es' => $dispPrev->formatEs(),
                        'realizado' => '0.00',
                        'realizado_es' => '0,00',
                        'pct' => null,
                    ],
                    'saldo_final' => [
                        'previsto' => $saldoPrev->toString(),
                        'previsto_es' => $saldoPrev->formatEs(),
                        'realizado' => '0.00',
                        'realizado_es' => '0,00',
                    ],
                ],
            ];
        }

        $ingresos = $sum(['11', '12', '13', '14', '15']);
        $gastos = $sum(['201', '202', '203', '204', '205', '206', '207', '208', '209', '210', '211', '212', '213', '214', '215']);
        $ini = $index['32'] ?? $sum(['32']);
        $saldoIngGastosPrev = (new Dinero($ingresos['previsto']))->sub(new Dinero($gastos['previsto']));
        $dispPrev = $saldoIngGastosPrev->add(new Dinero($ini['previsto']));

        return [
            'centro' => $centroEtiqueta,
            'etiqueta_ejercicio' => $etiquetaEjercicio,
            'plan_contable' => CatalogoPlanesContables::H16N,
            'lineas' => $lineas,
            'totales' => [
                'ingresos' => $ingresos,
                'gastos' => $gastos,
                'saldo_ingresos_gastos' => [
                    'previsto' => $saldoIngGastosPrev->toString(),
                    'previsto_es' => $saldoIngGastosPrev->formatEs(),
                    'realizado' => '0.00',
                    'realizado_es' => '0,00',
                ],
                'disponible' => [
                    'previsto' => $dispPrev->toString(),
                    'previsto_es' => $dispPrev->formatEs(),
                    'realizado' => '0.00',
                    'realizado_es' => '0,00',
                ],
            ],
        ];
    }

    /** @return list<array{codigo:string,etiqueta:string}> */
    private function destinosNombrados(int $centroId): array
    {
        $map = $this->destinosSg?->nombrados($centroId);
        if (!is_array($map)) {
            return [];
        }
        $out = [];
        foreach ($map as $codigo => $etiqueta) {
            $out[] = ['codigo' => (string) $codigo, 'etiqueta' => $etiqueta];
        }

        return $out;
    }
}
