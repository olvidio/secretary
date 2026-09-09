<?php

declare(strict_types=1);

namespace src\importacion\domain\entity;

final class FilaImportada
{
    public function __construct(
        public readonly int $ejercicioId,
        public readonly string $hoja,
        public readonly int $fila,
        public readonly string $hashContenido,
        public readonly ?int $asientoId,
        public readonly ?int $id = null,
    ) {
    }

    public function clave(): string
    {
        return $this->hoja . ':' . $this->fila;
    }
}
