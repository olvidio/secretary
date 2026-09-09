<?php

declare(strict_types=1);

namespace src\ambito\domain\contracts;

use src\ambito\domain\entity\CuentaFisica;

interface CuentaFisicaRepository
{
    /** @return list<CuentaFisica> */
    public function listarDeCentro(int $centroId): array;

    /** @return list<CuentaFisica> */
    public function listarActivasDeCentro(int $centroId, ?string $tipo = null): array;

    public function porId(int $id): ?CuentaFisica;

    public function maxOrdenPorTipo(int $centroId, string $tipo): int;

    public function contarActivasPorTipo(int $centroId, string $tipo): int;

    public function guardar(CuentaFisica $cuentaFisica): CuentaFisica;
}
