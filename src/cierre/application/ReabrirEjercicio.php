<?php

declare(strict_types=1);

namespace src\cierre\application;

use InvalidArgumentException;
use src\ambito\application\SincronizarConfiguracionConEjercicio;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;
use src\asientos\domain\contracts\AsientoRepository;

/**
 * Reabre un ejercicio cerrado para corrección (D12). Dos caminos:
 * - Anterior: si un ejercicio posterior apunta aquí y solo tiene aperturas.
 * - Posterior: si el anterior ya está cerrado y no hay otro abierto.
 */
final class ReabrirEjercicio
{
    public function __construct(
        private readonly EjercicioRepository $ejercicios,
        private readonly AsientoRepository $asientos,
        private readonly SincronizarConfiguracionConEjercicio $sincronizarConfig,
    ) {
    }

    public function ejecutar(int $ejercicioId): Ejercicio
    {
        $ejercicio = $this->ejercicios->porId($ejercicioId);
        if ($ejercicio === null) {
            throw new InvalidArgumentException('Ejercicio no encontrado');
        }
        if ($ejercicio->estado === 'abierto') {
            throw new InvalidArgumentException('El ejercicio ya está abierto');
        }

        $posterior = $this->ejercicios->posteriorConAnteriorId($ejercicio->id ?? 0);
        if ($posterior !== null) {
            return $this->reabrirAnterior($ejercicio, $posterior);
        }

        return $this->reabrirPosterior($ejercicio);
    }

    private function reabrirAnterior(Ejercicio $ejercicio, Ejercicio $posterior): Ejercicio
    {
        if ($posterior->id === null) {
            throw new InvalidArgumentException('Ejercicio posterior inválido');
        }
        if ($this->asientos->contarNoApertura($posterior->id) > 0) {
            throw new InvalidArgumentException(sprintf(
                'El ejercicio %s ya tiene movimientos; no se puede reabrir %s',
                $posterior->etiqueta,
                $ejercicio->etiqueta,
            ));
        }

        if ($posterior->estado === 'abierto') {
            $this->ejercicios->guardar(new Ejercicio(
                $posterior->id,
                $posterior->centroId,
                $posterior->etiqueta,
                $posterior->fechaInicio,
                $posterior->fechaFin,
                $posterior->fechaCorte,
                'cerrado',
                $posterior->ejercicioAnteriorId,
            ));
        }

        $reabierto = $this->ejercicios->guardar(new Ejercicio(
            $ejercicio->id,
            $ejercicio->centroId,
            $ejercicio->etiqueta,
            $ejercicio->fechaInicio,
            $ejercicio->fechaFin,
            $ejercicio->fechaFin,
            'abierto',
            $ejercicio->ejercicioAnteriorId,
        ));
        $this->sincronizarConfig->ejecutar($reabierto);

        return $reabierto;
    }

    private function reabrirPosterior(Ejercicio $ejercicio): Ejercicio
    {
        if ($ejercicio->ejercicioAnteriorId === null) {
            throw new InvalidArgumentException('Este ejercicio no se puede reabrir: no tiene ejercicio anterior');
        }
        $anterior = $this->ejercicios->porId($ejercicio->ejercicioAnteriorId);
        if ($anterior === null) {
            throw new InvalidArgumentException('Ejercicio anterior no encontrado');
        }
        if ($anterior->estado !== 'cerrado') {
            throw new InvalidArgumentException('Cierre primero el ejercicio ' . $anterior->etiqueta);
        }

        $abierto = $this->ejercicios->abiertoDe($ejercicio->centroId);
        if ($abierto !== null) {
            throw new InvalidArgumentException(
                'Ya hay un ejercicio abierto (' . $abierto->etiqueta . '); no puede haber dos abiertos'
            );
        }

        $reabierto = $this->ejercicios->guardar(new Ejercicio(
            $ejercicio->id,
            $ejercicio->centroId,
            $ejercicio->etiqueta,
            $ejercicio->fechaInicio,
            $ejercicio->fechaFin,
            $ejercicio->fechaInicio,
            'abierto',
            $ejercicio->ejercicioAnteriorId,
        ));
        $this->sincronizarConfig->ejecutar($reabierto);

        return $reabierto;
    }
}
