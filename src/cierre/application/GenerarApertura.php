<?php

declare(strict_types=1);

namespace src\cierre\application;

use InvalidArgumentException;
use RuntimeException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\cierre\domain\services\ConstructorAsientoApertura;

/** Regenera los asientos de apertura del ejercicio destino (D12), idempotente. */
final class GenerarApertura
{
    public function __construct(
        private readonly EjercicioRepository $ejercicios,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    /** @return list<Asiento> */
    public function ejecutar(int $ejercicioId): array
    {
        $destino = $this->ejercicios->porId($ejercicioId);
        if ($destino === null) {
            throw new InvalidArgumentException('Ejercicio no encontrado');
        }
        if ($destino->ejercicioAnteriorId === null) {
            throw new InvalidArgumentException('Este ejercicio no tiene anterior: la apertura es manual');
        }
        if ($destino->estado !== 'abierto') {
            throw new InvalidArgumentException('Solo se puede regenerar la apertura en un ejercicio abierto');
        }
        if ($destino->id === null) {
            throw new RuntimeException('Ejercicio destino sin id');
        }

        $anterior = $this->ejercicios->porId($destino->ejercicioAnteriorId);
        if ($anterior === null || $anterior->id === null) {
            throw new InvalidArgumentException('Ejercicio anterior no encontrado');
        }
        if ($anterior->estado !== 'cerrado') {
            throw new InvalidArgumentException('El ejercicio anterior debe estar cerrado');
        }

        if ($this->asientos->hayDescuadrados($anterior->id)) {
            throw new InvalidArgumentException(
                'El ejercicio anterior tiene asientos descuadrados; corríjalos antes de generar la apertura'
            );
        }

        $fechaFin = $anterior->fechaFin->format('Y-m-d');
        $saldos = $this->asientos->saldosPorCuenta(
            $destino->centroId,
            $anterior->id,
            null,
            $fechaFin,
        );

        $pendientes = [];
        foreach (['P', 'G'] as $libro) {
            $patrimonio = $this->cuentaPatrimonio($destino, $libro);
            if ($patrimonio === null || $patrimonio->id === null) {
                throw new RuntimeException('Falta cuenta de patrimonio para el libro ' . $libro);
            }
            $movimientos = ConstructorAsientoApertura::movimientos($libro, $saldos, $patrimonio->id);
            if ($movimientos === []) {
                continue;
            }

            $pendientes[] = new Asiento(
                null,
                $destino->id,
                $libro,
                null,
                $destino->fechaInicio,
                'Apertura de ejercicio',
                'apertura',
                'manual',
                null,
                $movimientos,
                $libro === 'G' ? '32' : null,
            );
        }

        $aperturas = $this->asientos->reemplazarAperturas($destino->id, $pendientes);
        $this->asientos->traspasarPeriodificacion(
            $anterior->id,
            $destino->id,
            $destino->fechaInicio,
            $destino->fechaFin,
        );

        return $aperturas;
    }

    private function cuentaPatrimonio(Ejercicio $destino, string $libro): ?\src\ambito\domain\entity\Cuenta
    {
        if ($libro === 'G') {
            return $this->cuentas->buscar($destino->centroId, null, 'G', '32');
        }

        return $this->cuentas->buscar($destino->centroId, null, 'P', 'RESULTADO.ANT');
    }
}
