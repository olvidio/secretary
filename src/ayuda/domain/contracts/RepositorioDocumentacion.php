<?php

declare(strict_types=1);

namespace src\ayuda\domain\contracts;

use src\ayuda\domain\entity\DocumentoAyuda;

interface RepositorioDocumentacion
{
    /** @return list<DocumentoAyuda> */
    public function todos(): array;

    /** Cambia en cuanto cambia cualquier documento: caduca las respuestas guardadas. */
    public function version(): string;
}
