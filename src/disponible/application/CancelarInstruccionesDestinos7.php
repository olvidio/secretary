<?php

declare(strict_types=1);

namespace src\disponible\application;

use DateTimeImmutable;
use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\disponible\domain\contracts\SaldoDisponibleRepository;
use src\mensajes\application\RefrescarBandejaDePersona;

/** Retira instrucciones 7 pendientes (sin apunte) y devuelve el disponible operativo reservado. */
final class CancelarInstruccionesDestinos7
{
    public function __construct(
        private readonly AsignacionLaboresRepository $asignaciones,
        private readonly SaldoDisponibleRepository $saldos,
        private readonly RefrescarBandejaDePersona $refrescarBandeja,
    ) {
    }

    public function ejecutar(int $centroId, int $personaId): void
    {
        $res = $this->asignaciones->descartarInstruccionesPendientes($centroId, $personaId);
        if ($res === null || $res['cents'] <= 0) {
            $this->refrescarBandeja->ejecutar($personaId);

            return;
        }
        $fecha = new DateTimeImmutable('today');
        $this->saldos->aplicar(
            $centroId,
            $personaId,
            $res['cents'],
            $fecha->format('Y-m-d'),
            'instruccion_descartada',
            $res['ejercicio_id'],
            null,
            null,
            'Instrucción destinos 7 retirada',
        );
        $this->refrescarBandeja->ejecutar($personaId);
    }
}
