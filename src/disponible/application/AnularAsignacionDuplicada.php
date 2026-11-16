<?php

declare(strict_types=1);

namespace src\disponible\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\asientos\domain\contracts\AsientoRepository;
use src\disponible\domain\services\DetectorLaboresDuplicadas;
use src\shared\domain\value_objects\Dinero;

/**
 * Quita una asignación de destinos 7 que está repetida (el gasto 7 ya entró
 * por otro apunte, normalmente la remesa): anula el asiento de la asignación
 * y su liquidación de c/c. El 111 queda igual (las dos se compensan) y la c/c
 * de la persona recupera el importe.
 */
final class AnularAsignacionDuplicada
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /** @return array{asignacion_id:int, liquidacion_id:int, importe_es:string} */
    public function ejecutar(int $asientoId): array
    {
        $contexto = $this->ambito->ejecutar();
        $asiento = $this->asientos->porId($asientoId);
        if ($asiento === null
            || $asiento->ejercicioId !== $contexto->ejercicioId
            || $asiento->libro !== 'P'
            || $asiento->origen !== 'asignacion'
            || $asiento->tipo !== 'normal'
            || $asiento->personaId === null) {
            throw new InvalidArgumentException(_('No es una asignación de destinos 7 de este ejercicio.'));
        }

        $filas = $this->asientos->movimientosLabores($contexto->centroId, $contexto->ejercicioId, '9999-12-31');
        $repetido = 0;
        foreach (DetectorLaboresDuplicadas::detectar($filas) as $par) {
            if ($par['asignacion']['asiento_id'] === $asientoId) {
                $repetido += $par['cents'];
            }
        }
        if ($repetido === 0) {
            throw new InvalidArgumentException(_('Esta asignación ya no aparece repetida.'));
        }
        $total = DetectorLaboresDuplicadas::totalAsiento($filas, $asientoId);
        if ($repetido !== $total) {
            throw new InvalidArgumentException(_('Solo una parte de esta asignación está repetida; no se puede anular entera. Hay que revisarla a mano.'));
        }

        $fecha = $asiento->fecha->format('Y-m-d');
        $liquidacion = $this->asientos->buscarLiquidacionCc($contexto->ejercicioId, $asiento->personaId, $fecha, $total);
        if ($liquidacion === null) {
            throw new InvalidArgumentException(_('No se encuentra la liquidación de c/c de esta asignación; no se anula para no descuadrar el 111.'));
        }
        $this->asientos->anularAsignacionConLiquidacion($asientoId, $liquidacion);

        return [
            'asignacion_id' => $asientoId,
            'liquidacion_id' => $liquidacion,
            'importe_es' => Dinero::fromCents($total)->formatEs(),
        ];
    }
}
