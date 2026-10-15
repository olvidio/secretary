<?php

declare(strict_types=1);

namespace src\importacion\domain\contracts;

/** Sabe si un extracto tabulado (CSV parseado o Excel) corresponde a este banco. */
interface ReconocedorExtractoBanco
{
    /**
     * @param list<list<string>> $filas
     */
    public function reconoceFilas(array $filas): bool;
}
