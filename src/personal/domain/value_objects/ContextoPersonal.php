<?php

declare(strict_types=1);

namespace src\personal\domain\value_objects;

final class ContextoPersonal
{
    public function __construct(
        public readonly int $centroId,
        public readonly int $ejercicioId,
        public readonly int $personaId,
    ) {
    }
}
