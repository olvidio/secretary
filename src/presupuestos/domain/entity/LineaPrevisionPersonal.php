<?php

declare(strict_types=1);

namespace src\presupuestos\domain\entity;

final class LineaPrevisionPersonal
{
    public function __construct(
        public readonly int $ejercicioId,
        public readonly int $personaId,
        public readonly string $conceptoCodigo,
        public readonly int $previstoCents,
    ) {
    }
}
