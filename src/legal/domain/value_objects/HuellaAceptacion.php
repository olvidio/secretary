<?php

declare(strict_types=1);

namespace src\legal\domain\value_objects;

final class HuellaAceptacion
{
    /**
     * @param array<string, mixed>|null $extra
     */
    public function __construct(
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly string $idioma = 'es',
        public readonly ?string $email = null,
        public readonly ?string $alias = null,
        public readonly ?int $centroId = null,
        public readonly ?int $personaId = null,
        public readonly ?string $tokenHash = null,
        public readonly ?array $extra = null,
    ) {
    }
}
