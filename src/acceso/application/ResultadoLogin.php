<?php

declare(strict_types=1);

namespace src\acceso\application;

final class ResultadoLogin
{
    /**
     * @param list<array{centro_id:int, codigo:string, nombre:string, rol:string}> $centros
     */
    public function __construct(
        public readonly string $estado,
        public readonly string $mensaje = '',
        public readonly ?int $identidadId = null,
        public readonly string $nombre = '',
        public readonly string $email = '',
        public readonly string $nivel = '',
        public readonly array $centros = [],
        public readonly ?int $personaId = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->estado !== 'fallo';
    }
}
