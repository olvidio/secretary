<?php

declare(strict_types=1);

namespace src\mensajes\application;

use src\mensajes\domain\contracts\MensajeRepository;

final class MarcarMensajesLeidos
{
    public function __construct(private readonly MensajeRepository $mensajes)
    {
    }

    /** @param list<int> $ids */
    public function ejecutar(int $identidadId, array $ids): void
    {
        $this->mensajes->marcarLeidos($identidadId, $ids);
    }
}
