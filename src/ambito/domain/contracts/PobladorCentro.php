<?php

declare(strict_types=1);

namespace src\ambito\domain\contracts;

/** Siembra plan maestro, tesorería y puentes de un centro (Fase 2 / Fase 9). */
interface PobladorCentro
{
    public function ejecutar(int $centroId): void;
}
