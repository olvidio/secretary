<?php

declare(strict_types=1);

namespace src\informes\domain\contracts;

interface EstadisticasSg
{
    /**
     * @return array{num_s:int, aportaciones:int, sin_aportacion:int}
     */
    public function aportacionesOrdinarias(int $centroId, int $ejercicioId, string $desde, string $hasta): array;
}
