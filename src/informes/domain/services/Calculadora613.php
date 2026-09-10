<?php

declare(strict_types=1);

namespace src\informes\domain\services;

use src\plan\domain\services\CatalogoPlanesContables;
use src\plan\domain\services\Estructura613P;
use src\presupuestos\domain\entity\LineaPresupuesto;
use src\shared\domain\value_objects\Dinero;
use src\shared\domain\value_objects\PeriodoEjercicio;

final class Calculadora613
{
    /**
     * @param array<string, int> $realizadoPorCodigo concepto => cents (positivo como Excel)
     * @param list<LineaPresupuesto> $presupuesto
     * @param list<array{codigo:string,etiqueta:string,codigos:list<string>}> $lineas
     * @return list<array<string, mixed>>
     */
    public static function lineas(
        array $realizadoPorCodigo,
        array $presupuesto,
        PeriodoEjercicio $periodo,
        array $lineas,
        int $saldoCodigo9Cents = 0,
    ): array {
        $mesesTotales = max(1, $periodo->mesesTotales());
        $mesesTranscurridos = max(0, $periodo->mesesTranscurridos());
        $prevIndex = [];
        foreach ($presupuesto as $p) {
            $prevIndex[$p->conceptoCodigo] = $p->previsto;
        }
        $out = [];
        foreach ($lineas as $def) {
            $previsto = Dinero::zero();
            foreach ($def['codigos'] as $cod) {
                $previsto = $previsto->add($prevIndex[$cod] ?? Dinero::zero());
            }
            $previstoProrrateado = $previsto->mulRatio((string) $mesesTranscurridos, (string) $mesesTotales);
            $realizado = Dinero::zero();
            if ($def['codigo'] === '9') {
                $realizado = Dinero::fromCents($saldoCodigo9Cents);
            } else {
                foreach ($def['codigos'] as $cod) {
                    $realizado = $realizado->add(Dinero::fromCents($realizadoPorCodigo[$cod] ?? 0));
                }
            }
            $pct = null;
            if (!$previstoProrrateado->isZero()) {
                $pct = (float) $realizado->toString() / (float) $previstoProrrateado->toString();
            }
            $out[] = [
                'codigo' => $def['codigo'],
                'etiqueta' => $def['etiqueta'],
                'previsto' => $previstoProrrateado->toString(),
                'previsto_es' => $previstoProrrateado->formatEs(),
                'realizado' => $realizado->toString(),
                'realizado_es' => $realizado->formatEs(),
                'pct' => $pct,
            ];
        }

        return $out;
    }

    /**
     * @param list<array{codigo:string,etiqueta:string}>|null $partidasLabores
     * @return list<array{codigo:string,etiqueta:string,codigos:list<string>}>
     */
    public static function estructuraP(?array $partidasLabores = null): array
    {
        if ($partidasLabores === null) {
            $partidasLabores = CatalogoPlanesContables::partidasLaboresPorDefecto();
        }

        return Estructura613P::construir($partidasLabores);
    }

    /**
     * @return list<array{codigo:string,etiqueta:string,codigos:list<string>}>
     */
    public static function estructuraG(): array
    {
        return [
            ['codigo' => '11', 'etiqueta' => 'Vivienda / local', 'codigos' => ['11']],
            ['codigo' => '12', 'etiqueta' => 'Donativos', 'codigos' => ['12']],
            ['codigo' => '13', 'etiqueta' => 'Otras ayudas', 'codigos' => ['13']],
            ['codigo' => '14', 'etiqueta' => 'Ayudas de los del ctr para la sede', 'codigos' => ['14']],
            ['codigo' => '15', 'etiqueta' => 'Ayudas extraordinarias', 'codigos' => ['15']],
            ['codigo' => '201', 'etiqueta' => 'Agua', 'codigos' => ['201']],
            ['codigo' => '202', 'etiqueta' => 'Luz', 'codigos' => ['202']],
            ['codigo' => '203', 'etiqueta' => 'Teléfono', 'codigos' => ['203']],
            ['codigo' => '204', 'etiqueta' => 'Gas', 'codigos' => ['204']],
            ['codigo' => '205', 'etiqueta' => 'Otros suministros', 'codigos' => ['205']],
            ['codigo' => '206', 'etiqueta' => 'Comunidad vecinos, seguros e impuestos', 'codigos' => ['206']],
            ['codigo' => '207', 'etiqueta' => 'Alquileres', 'codigos' => ['207']],
            ['codigo' => '208', 'etiqueta' => 'Instalación y conservación', 'codigos' => ['208']],
            ['codigo' => '209', 'etiqueta' => 'Administración: sueldos y Seguridad Social', 'codigos' => ['209']],
            ['codigo' => '210', 'etiqueta' => 'Administración: resto', 'codigos' => ['210']],
            ['codigo' => '211', 'etiqueta' => 'Asociación', 'codigos' => ['211']],
            ['codigo' => '212', 'etiqueta' => 'Suscripciones, papelería, libros', 'codigos' => ['212']],
            ['codigo' => '213', 'etiqueta' => 'Coches', 'codigos' => ['213']],
            ['codigo' => '214', 'etiqueta' => 'Reyes', 'codigos' => ['214']],
            ['codigo' => '215', 'etiqueta' => 'Otros', 'codigos' => ['215']],
            ['codigo' => '32', 'etiqueta' => 'Disponible al inicio', 'codigos' => ['32']],
        ];
    }
}
