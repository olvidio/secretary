<?php

declare(strict_types=1);

namespace src\personal\domain\contracts;

interface RemanenteRepository
{
    public function dePersona(int $personaId): int;

    public function guardar(int $personaId, int $cents): void;
}
