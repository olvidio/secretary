<?php

declare(strict_types=1);

namespace src\mensajes\application;

use src\mensajes\domain\contracts\MensajeRepository;

final class ContarMensajesNoLeidos
{
    public function __construct(
        private readonly SincronizarBandeja $sincronizar,
        private readonly MensajeRepository $mensajes,
    ) {
    }

    public function ejecutar(int $identidadId): int
    {
        $this->sincronizar->ejecutar($identidadId);

        return $this->mensajes->contarNoLeidos($identidadId);
    }
}
