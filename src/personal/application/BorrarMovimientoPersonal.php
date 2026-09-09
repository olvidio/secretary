<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use src\asientos\domain\contracts\AsientoRepository;

final class BorrarMovimientoPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly AsientoRepository $asientos,
    ) {
    }

    public function ejecutar(int $id): void
    {
        $ctx = $this->ambito->ejecutar();
        $asiento = $this->asientos->porId($id);
        if ($asiento === null || $asiento->libro !== 'X' || $asiento->personaId !== $ctx->personaId) {
            throw new InvalidArgumentException('Movimiento no encontrado');
        }
        $this->asientos->borrar($id);
    }
}
