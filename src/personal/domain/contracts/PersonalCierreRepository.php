<?php

declare(strict_types=1);

namespace src\personal\domain\contracts;

use DateTimeImmutable;

interface PersonalCierreRepository
{
    /** @return array{dia_cierre: ?int, dia_habil: bool} */
    public function defectoDe(int $personaId): array;

    public function guardarDefecto(int $personaId, ?int $diaCierre, bool $diaHabil): void;

    public function fechaMes(int $personaId, int $anio, int $mes): ?DateTimeImmutable;

    public function guardarMes(int $personaId, int $anio, int $mes, DateTimeImmutable $fecha): void;

    public function borrarMes(int $personaId, int $anio, int $mes): void;
}
