<?php

declare(strict_types=1);

namespace src\legal\domain\entity;

use DateTimeImmutable;
use src\legal\domain\value_objects\HuellaAceptacion;

final class AceptacionLegal
{
    public function __construct(
        public readonly ?int $identidadId,
        public readonly string $canal,
        public readonly string $condicionesVersion,
        public readonly string $condicionesHash,
        public readonly string $privacidadVersion,
        public readonly string $privacidadHash,
        public readonly string $textoCasilla,
        public readonly HuellaAceptacion $huella,
        public readonly DateTimeImmutable $momento,
    ) {
    }
}
