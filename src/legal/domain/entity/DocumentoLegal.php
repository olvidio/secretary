<?php

declare(strict_types=1);

namespace src\legal\domain\entity;

final class DocumentoLegal
{
    public function __construct(
        public readonly string $tipo,
        public readonly string $version,
        public readonly string $idioma,
        public readonly string $hashSha256,
        public readonly string $texto,
    ) {
    }
}
