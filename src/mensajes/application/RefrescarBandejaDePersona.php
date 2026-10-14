<?php

declare(strict_types=1);

namespace src\mensajes\application;

use src\acceso\domain\contracts\IdentidadRepository;

/** Sincroniza la bandeja de todas las identidades vinculadas a una persona del centro. */
final class RefrescarBandejaDePersona
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly SincronizarBandeja $sincronizar,
    ) {
    }

    public function ejecutar(int $personaId): void
    {
        foreach ($this->identidades->identidadesDePersona($personaId) as $identidadId) {
            $this->sincronizar->ejecutar($identidadId);
        }
    }
}
