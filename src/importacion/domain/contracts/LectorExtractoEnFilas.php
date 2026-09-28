<?php

declare(strict_types=1);

namespace src\importacion\domain\contracts;

use src\importacion\domain\value_objects\LineaExtractoBanco;

/** Extracto que también llega como tabla (Excel ya convertido a celdas). */
interface LectorExtractoEnFilas extends LectorCsvBanco
{
    /**
     * @param list<list<string>> $filas
     * @return list<LineaExtractoBanco>
     */
    public function leerFilas(array $filas): array;
}
