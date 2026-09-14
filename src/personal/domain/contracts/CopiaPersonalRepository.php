<?php

declare(strict_types=1);

namespace src\personal\domain\contracts;

interface CopiaPersonalRepository
{
    /**
     * @return array<string, mixed>
     */
    public function exportar(int $centroId, int $personaId): array;

    /**
     * @param array<string, mixed> $datos
     */
    public function restaurar(int $centroId, int $personaId, array $datos): void;
}
