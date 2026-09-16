<?php

declare(strict_types=1);

namespace src\personal\domain\contracts;

interface PersonalBancoRepository
{
    public function dePersona(int $personaId): ?string;

    public function guardar(int $personaId, string $banco): void;
}
