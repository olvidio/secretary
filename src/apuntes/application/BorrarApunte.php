<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use src\asientos\domain\contracts\AsientoRepository;

final class BorrarApunte
{
    public function __construct(private readonly AsientoRepository $asientos)
    {
    }

    public function ejecutar(int $id): void
    {
        $asiento = $this->asientos->porId($id);
        if ($asiento === null || $asiento->libro === 'X') {
            throw new InvalidArgumentException('Apunte no encontrado');
        }
        if ($asiento->origen === 'remesa' || $asiento->tipo === 'remesa' || $asiento->remesaId !== null) {
            throw new InvalidArgumentException('Un asiento de remesa no se borra a mano; se corrige reenviando');
        }
        $this->asientos->borrar($id);
    }
}
