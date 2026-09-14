<?php

declare(strict_types=1);

namespace src\personal\application;

use src\personal\domain\contracts\PersonalCierreRepository;
use src\personal\domain\services\PeriodoPersonal;

final class ResolverPeriodoPersonal
{
    public function __construct(
        private readonly PersonalCierreRepository $cierres,
    ) {
    }

    /**
     * @return array{
     *     anio: int,
     *     mes: int,
     *     desde: string,
     *     hasta: string,
     *     fecha_cierre: string,
     *     dia_cierre: ?int,
     *     dia_habil: bool,
     *     fecha_mes: ?string,
     *     es_personalizado: bool
     * }
     */
    public function ejecutar(int $personaId, int $anio, int $mes): array
    {
        PeriodoPersonal::validar($anio, $mes);
        $defecto = $this->cierres->defectoDe($personaId);
        $fechaMes = $this->cierres->fechaMes($personaId, $anio, $mes);
        $periodo = PeriodoPersonal::periodo(
            $anio,
            $mes,
            $defecto['dia_cierre'],
            $defecto['dia_habil'],
            $fechaMes,
        );

        return [
            'anio' => $anio,
            'mes' => $mes,
            'desde' => $periodo['desde']->format('Y-m-d'),
            'hasta' => $periodo['hasta']->format('Y-m-d'),
            'fecha_cierre' => $periodo['fecha_cierre']->format('Y-m-d'),
            'dia_cierre' => $defecto['dia_cierre'],
            'dia_habil' => $defecto['dia_habil'],
            'fecha_mes' => $fechaMes?->format('Y-m-d'),
            'es_personalizado' => $fechaMes !== null,
        ];
    }
}
