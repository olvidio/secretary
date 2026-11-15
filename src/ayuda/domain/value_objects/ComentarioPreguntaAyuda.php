<?php

declare(strict_types=1);

namespace src\ayuda\domain\value_objects;

/** Lo que un usuario dijo al marcar una respuesta como «no me ha servido». */
final class ComentarioPreguntaAyuda
{
    public function __construct(
        public readonly string $texto,
        public readonly string $quien,
        public readonly string $fecha,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return ['texto' => $this->texto, 'quien' => $this->quien, 'fecha' => $this->fecha];
    }
}
