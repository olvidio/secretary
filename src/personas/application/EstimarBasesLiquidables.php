<?php

declare(strict_types=1);

namespace src\personas\application;

use RuntimeException;
use src\personas\domain\services\ResolverBaseLiquidable;
use src\presupuestos\application\ConstruirHojaPrevision;
use src\presupuestos\domain\contracts\PrevisionPersonalRepository;
use src\presupuestos\domain\services\ProyectorPrevisionPersonal;
use src\shared\domain\value_objects\Dinero;

/**
 * 111 de la previsión personal, o el ingreso 111 proyectado a fin de ejercicio.
 *
 * @phpstan-type Estimacion array{cents:int, origen:string, importe:string}
 */
final class EstimarBasesLiquidables
{
    public const CONCEPTO_TRABAJO = '111';

    public function __construct(
        private readonly ConstruirHojaPrevision $hoja,
        private readonly PrevisionPersonalRepository $prevision,
    ) {
    }

    /**
     * @return array<int, Estimacion> persona_id => estimación
     */
    public function deCentro(): array
    {
        try {
            $datos = $this->hoja->datosCentro();
        } catch (RuntimeException) {
            return [];
        }
        $ejercicio = $datos['ejercicio'];
        if ($ejercicio->id === null) {
            return [];
        }
        $previsto111 = [];
        foreach ($this->prevision->listarDeEjercicio($ejercicio->id) as $linea) {
            if ($linea->conceptoCodigo !== self::CONCEPTO_TRABAJO) {
                continue;
            }
            $previsto111[$linea->personaId] = $linea->previstoCents;
        }
        $mesesT = $datos['periodo']->mesesTranscurridos();
        $mesesTot = $datos['periodo']->mesesTotales();
        $ids = array_unique(array_merge(
            array_keys($previsto111),
            array_keys($datos['acumulado']),
            array_keys($datos['anterior_total']),
        ));
        $out = [];
        foreach ($ids as $personaId) {
            $pid = (int) $personaId;
            $acum = (int) ($datos['acumulado'][$pid][self::CONCEPTO_TRABAJO] ?? 0);
            $antTot = (int) ($datos['anterior_total'][$pid][self::CONCEPTO_TRABAJO] ?? 0);
            $antPer = (int) ($datos['anterior_periodo'][$pid][self::CONCEPTO_TRABAJO] ?? 0);
            $proyectado = ProyectorPrevisionPersonal::proyectar(
                self::CONCEPTO_TRABAJO,
                $acum,
                $antTot,
                $antPer,
                $mesesT,
                $mesesTot,
            );
            $res = ResolverBaseLiquidable::de(null, $previsto111[$pid] ?? null, $proyectado);
            if ($res === null) {
                continue;
            }
            $out[$pid] = [
                'cents' => $res['cents'],
                'origen' => $res['origen'],
                'importe' => Dinero::fromCents($res['cents'])->toString(),
            ];
        }

        return $out;
    }
}
