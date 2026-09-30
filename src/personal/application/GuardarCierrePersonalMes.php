<?php

declare(strict_types=1);

namespace src\personal\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\personal\domain\contracts\PersonalCierreRepository;
use src\personal\domain\services\PeriodoPersonal;

final class GuardarCierrePersonalMes
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly PersonalCierreRepository $cierres,
        private readonly ResolverPeriodoPersonal $periodo,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $anio = (int) ($datos['anio'] ?? 0);
        $mes = (int) ($datos['mes'] ?? 0);
        PeriodoPersonal::validar($anio, $mes);
        $fechaRaw = trim((string) ($datos['fecha_cierre'] ?? ''));
        if ($fechaRaw === '') {
            throw new InvalidArgumentException(_("Indique la fecha de cierre"));
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaRaw);
        if ($fecha === false) {
            throw new InvalidArgumentException(_("Fecha de cierre no válida"));
        }
        $defecto = $this->cierres->defectoDe($ctx->personaId);
        [$anioAnt, $mesAnt] = PeriodoPersonal::mesAnterior($anio, $mes);
        $fechaAnt = $this->cierres->fechaMes($ctx->personaId, $anioAnt, $mesAnt);
        PeriodoPersonal::periodo(
            $anio,
            $mes,
            $defecto['dia_cierre'],
            $defecto['dia_habil'],
            $fecha,
            $fechaAnt,
        );
        $this->cierres->guardarMes($ctx->personaId, $anio, $mes, $fecha);

        return $this->periodo->ejecutar($ctx->personaId, $anio, $mes);
    }
}
