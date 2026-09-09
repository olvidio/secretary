<?php

declare(strict_types=1);

namespace src\cierre\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;

/** Cierra un ejercicio abierto (D12): sin más asientos, corte = fin real. */
final class CerrarEjercicio
{
    public function __construct(private readonly EjercicioRepository $ejercicios)
    {
    }

    public function ejecutar(int $ejercicioId): Ejercicio
    {
        $ejercicio = $this->ejercicios->porId($ejercicioId);
        if ($ejercicio === null) {
            throw new InvalidArgumentException('Ejercicio no encontrado');
        }
        if ($ejercicio->estado !== 'abierto') {
            throw new InvalidArgumentException('Solo se puede cerrar un ejercicio abierto');
        }

        return $this->ejercicios->guardar(new Ejercicio(
            $ejercicio->id,
            $ejercicio->centroId,
            $ejercicio->etiqueta,
            $ejercicio->fechaInicio,
            $ejercicio->fechaFin,
            $ejercicio->fechaFin,
            'cerrado',
            $ejercicio->ejercicioAnteriorId,
        ));
    }
}
