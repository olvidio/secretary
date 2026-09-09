<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;
use src\asientos\domain\contracts\AsientoRepository;
use src\personal\application\ResolverPersonaActual;
use src\personal\domain\value_objects\ContextoPersonal;
use src\remesas\domain\entity\RemesaLinea;
use src\remesas\domain\services\AgregadorRemesaPersonal;
use src\remesas\domain\services\PeriodoMesRemesa;

final class ResolverMesRemesa
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    /**
     * @return array{
     *   ctx: ContextoPersonal,
     *   ejercicio: Ejercicio,
     *   anio: int,
     *   mes: int,
     *   lineas: list<RemesaLinea>
     * }
     */
    public function ejecutar(int $anio, int $mes): array
    {
        PeriodoMesRemesa::validar($anio, $mes);
        $ctx = $this->ambito->ejecutar();
        $probe = PeriodoMesRemesa::primerDia($anio, $mes);
        $ejercicio = $this->ejercicios->deCentroEnFecha($ctx->centroId, $probe);
        if ($ejercicio === null) {
            $ejercicio = $this->ejercicios->deCentroEnFecha(
                $ctx->centroId,
                PeriodoMesRemesa::ultimoDia($anio, $mes),
            );
        }
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new InvalidArgumentException('No hay ejercicio que cubra ese mes');
        }
        PeriodoMesRemesa::fechaAsiento($anio, $mes, $ejercicio);

        $cuentas = [];
        foreach ($this->cuentas->listarDePersona($ctx->centroId, $ctx->personaId, 'X') as $c) {
            if ($c->id !== null) {
                $cuentas[$c->id] = $c;
            }
        }
        $asientos = $this->asientos->listar($ejercicio->id, [
            'libro' => 'X',
            'persona_id' => $ctx->personaId,
            'desde' => PeriodoMesRemesa::primerDia($anio, $mes)->format('Y-m-d'),
            'hasta' => PeriodoMesRemesa::ultimoDia($anio, $mes)->format('Y-m-d'),
        ]);
        $lineas = AgregadorRemesaPersonal::agregar($asientos, $cuentas);

        return [
            'ctx' => $ctx,
            'ejercicio' => $ejercicio,
            'anio' => $anio,
            'mes' => $mes,
            'lineas' => $lineas,
        ];
    }
}
