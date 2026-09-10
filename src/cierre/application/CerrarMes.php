<?php

declare(strict_types=1);

namespace src\cierre\application;

use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\application\CrearApunte;
use src\asientos\domain\contracts\AsientoRepository;
use src\cierre\domain\services\RepartoCierre;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class CerrarMes
{
    public function __construct(
        private readonly ConfiguracionRepository $config,
        private readonly PersonaRepository $personas,
        private readonly AsientoRepository $asientos,
        private readonly CrearApunte $crearApunte,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(bool $confirmar = true): array
    {
        $cfg = $this->config->get();
        $cierre = $cfg->fechaCierre;
        $mes = (int) $cierre->format('n');
        $desde = $cierre->modify('first day of this month');
        $hasta = $cierre->modify('last day of this month');
        $contexto = $this->ambito->ejecutar();

        $realizado = $this->asientos->realizadoPorConcepto(
            $contexto->centroId,
            $contexto->ejercicioId,
            'G',
            $desde->format('Y-m-d'),
            $hasta->format('Y-m-d'),
            ['cierre'],
        );

        $gastos = Dinero::zero();
        for ($cod = 201; $cod <= 215; ++$cod) {
            $key = (string) $cod;
            $cents = array_key_exists($key, $realizado) ? $realizado[$key] : 0;
            $gastos = $gastos->add(Dinero::fromCents($cents));
        }

        $reparto = RepartoCierre::calcular($gastos, $this->personas->listarDeCentro($contexto->centroId), $mes);
        if (!$confirmar) {
            $prev = [];
            foreach ($reparto as $r) {
                $prev[] = [
                    'iniciales' => $r['persona']->iniciales,
                    'nombre' => $r['persona']->nombreCompleto(),
                    'importe' => $r['importe']->toString(),
                    'importe_es' => $r['importe']->formatEs(),
                ];
            }

            return [
                'preview' => true,
                'mes' => $mes,
                'gastos_generales' => $gastos->toString(),
                'gastos_generales_es' => $gastos->formatEs(),
                'tipo_cierre' => $cfg->tipoCierre,
                'lineas' => $prev,
            ];
        }

        $this->asientos->borrarCierresEntre($contexto->ejercicioId, $desde, $hasta);
        $conceptoP = $cfg->tipoCierre === 'necesidades' ? '6' : '21';
        $conceptoG = $cfg->tipoCierre === 'necesidades' ? '14' : '11';
        $obs = 'automático';
        $creados = [];
        foreach ($reparto as $r) {
            if ($r['importe']->isZero()) {
                continue;
            }
            $base = [
                'fecha' => $hasta->format('Y-m-d'),
                'origen' => 'A',
                'iniciales' => $r['persona']->iniciales,
                'observaciones' => $obs,
                'cantidad' => $r['importe']->toString(),
                'es_cierre' => true,
            ];
            foreach ($this->crearApunte->ejecutar($base + ['cuenta' => 'P', 'concepto_codigo' => $conceptoP]) as $a) {
                $creados[] = $a->toArray();
            }
            foreach ($this->crearApunte->ejecutar($base + ['cuenta' => 'G', 'concepto_codigo' => $conceptoG]) as $a) {
                $creados[] = $a->toArray();
            }
        }

        return [
            'preview' => false,
            'creados' => count($creados),
            'gastos_generales' => $gastos->toString(),
            'apuntes' => $creados,
        ];
    }
}
