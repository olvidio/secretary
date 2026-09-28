<?php

declare(strict_types=1);

namespace src\importacion\domain\contracts;

use src\importacion\domain\value_objects\LineaExtractoBanco;

interface LectorCsvBanco
{
    /**
     * @return list<LineaExtractoBanco>
     */
    public function leer(string $contenido): array;
}
