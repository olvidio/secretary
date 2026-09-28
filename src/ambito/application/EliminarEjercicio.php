<?php

declare(strict_types=1);

namespace src\ambito\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\EjercicioRepository;

final class EliminarEjercicio
{
    public function __construct(private readonly EjercicioRepository $repo)
    {
    }

    public function ejecutar(int $id, int $centroId): void
    {
        $ejercicio = $this->repo->porId($id);
        if ($ejercicio === null || $ejercicio->centroId !== $centroId) {
            throw new InvalidArgumentException(_('Ejercicio no encontrado'));
        }
        $posterior = $this->repo->posteriorConAnteriorId($id);
        if ($posterior !== null) {
            throw new InvalidArgumentException(sprintf(
                _('Elimine antes el ejercicio posterior %s'),
                $posterior->etiqueta
            ));
        }
        $this->repo->eliminar($id);
    }
}
