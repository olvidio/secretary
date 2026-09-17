<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;
use src\asientos\domain\contracts\AsientoRepository;
use src\personal\application\ResolverPeriodoPersonal;
use src\personal\application\ResolverPersonaActual;
use src\personal\domain\services\PeriodoPersonal;
use src\personal\domain\value_objects\ContextoPersonal;
use src\remesas\domain\entity\RemesaLinea;
use src\remesas\domain\services\AgregadorRemesaPersonal;

final class ResolverMesRemesa
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly ResolverPeriodoPersonal $periodoPersonal,
    ) {
    }

    /**
     * @return array{
     *   ctx: ContextoPersonal,
     *   ejercicio: Ejercicio,
     *   anio: int,
     *   mes: int,
     *   lineas: list<RemesaLinea>,
     *   tesoreria_cents: int,
     *   hasta: string
     * }
     */
    public function ejecutar(int $anio, int $mes): array
    {
        PeriodoPersonal::validar($anio, $mes);
        $ctx = $this->ambito->ejecutar();
        $periodo = $this->periodoPersonal->ejecutar($ctx->personaId, $anio, $mes);
        $desde = PeriodoPersonal::primerDia($anio, $mes);
        $hasta = \DateTimeImmutable::createFromFormat('!Y-m-d', $periodo['hasta']);
        if ($hasta === false) {
            throw new InvalidArgumentException(_("Periodo no válido"));
        }
        $ejercicio = $this->ejercicios->deCentroEnFecha($ctx->centroId, $desde);
        if ($ejercicio === null) {
            $ejercicio = $this->ejercicios->deCentroEnFecha($ctx->centroId, $hasta);
        }
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new InvalidArgumentException(_("No hay ejercicio que cubra ese mes"));
        }
        PeriodoPersonal::fechaAsiento($desde, $hasta, $ejercicio);

        $cuentas = [];
        foreach ($this->cuentas->listarDePersona($ctx->centroId, $ctx->personaId, 'X') as $c) {
            if ($c->id !== null) {
                $cuentas[$c->id] = $c;
            }
        }
        $asientos = $this->asientos->listar($ejercicio->id, [
            'libro' => 'X',
            'persona_id' => $ctx->personaId,
            'desde' => $periodo['desde'],
            'hasta' => $periodo['hasta'],
        ]);
        $lineas = AgregadorRemesaPersonal::agregar($asientos, $cuentas);
        $tesoreria = 0;
        foreach ($this->asientos->saldosPorCuenta(
            $ctx->centroId,
            (int) $ejercicio->id,
            null,
            $periodo['hasta'],
            'X',
        ) as $row) {
            if ((int) $row['persona_id'] !== $ctx->personaId) {
                continue;
            }
            if ($row['tipo'] === 'tesoreria' && in_array($row['codigo_maestro'], ['CAJA', 'BANCO'], true)) {
                $tesoreria += (int) $row['saldo_cents'];
            }
        }

        return [
            'ctx' => $ctx,
            'ejercicio' => $ejercicio,
            'anio' => $anio,
            'mes' => $mes,
            'lineas' => $lineas,
            'tesoreria_cents' => $tesoreria,
            'hasta' => $periodo['hasta'],
        ];
    }
}
