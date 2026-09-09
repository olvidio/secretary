<?php

declare(strict_types=1);

namespace src\importacion\domain\entity;

use src\apuntes\domain\entity\Apunte;
use src\importacion\domain\services\HashFilaImportacion;

final class FilaOrigenExcel
{
    public function __construct(
        public readonly string $hoja,
        public readonly int $fila,
        public readonly Apunte $apunte,
    ) {
    }

    public function hash(): string
    {
        return HashFilaImportacion::de($this->apunte);
    }

    public function clave(): string
    {
        return $this->hoja . ':' . $this->fila;
    }

    public function conApunte(Apunte $apunte): self
    {
        return new self($this->hoja, $this->fila, $apunte);
    }
}
