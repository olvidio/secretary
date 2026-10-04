<?php

declare(strict_types=1);

namespace src\apuntes\domain\contracts;

use src\apuntes\domain\entity\EntradaPeriodica;

interface EntradaPeriodicaRepository
{
    /** @return list<EntradaPeriodica> */
    public function listar(int $centroId): array;

    public function porId(int $centroId, int $id): ?EntradaPeriodica;

    public function guardar(EntradaPeriodica $entrada): EntradaPeriodica;

    public function borrar(int $centroId, int $id): void;

    /** @return list<string> fechas Y-m-d */
    public function fechasEjecutadas(int $centroId, int $entradaId): array;

    public function registrarEjecucion(int $entradaId, string $fechaYmd): void;
}
