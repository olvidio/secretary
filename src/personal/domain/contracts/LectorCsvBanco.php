<?php

declare(strict_types=1);

namespace src\personal\domain\contracts;

use src\personal\domain\value_objects\LineaExtractoBanco;

interface LectorCsvBanco
{
    /**
     * @return list<LineaExtractoBanco>
     */
    public function leer(string $contenido): array;
}
