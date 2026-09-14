<?php

declare(strict_types=1);

namespace src\ayuda\domain\entity;

use InvalidArgumentException;

/** Un fichero del manual de usuario. La clave es lo que la IA debe citar. */
final class DocumentoAyuda
{
    public function __construct(
        public readonly string $clave,
        public readonly string $titulo,
        public readonly string $texto,
    ) {
        if (trim($clave) === '') {
            throw new InvalidArgumentException('El documento de ayuda necesita una clave');
        }
        if (trim($titulo) === '') {
            throw new InvalidArgumentException('El documento de ayuda necesita un título');
        }
    }
}
