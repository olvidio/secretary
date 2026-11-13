<?php

declare(strict_types=1);

namespace src\arqueo\domain\contracts;

use DateTimeImmutable;
use src\arqueo\domain\entity\Arqueo;

interface ArqueoRepository
{
    public function ultimo(string $cuenta): ?Arqueo;

    public function ultimoPorFisica(int $cuentaFisicaId): ?Arqueo;

    public function ultimoEnCierre(int $ejercicioId, string $cuenta, DateTimeImmutable $fechaCierre): ?Arqueo;

    public function ultimoCajaEnCierre(
        int $ejercicioId,
        DateTimeImmutable $fechaCierre,
        ?int $cuentaFisicaId = null,
    ): ?Arqueo;

    /** Último recuento guardado de esa caja o banco para ese día. */
    public function deFisicaEnFecha(int $cuentaFisicaId, DateTimeImmutable $fecha): ?Arqueo;

    /**
     * Recuentos guardados de esa caja o banco, uno por día (el último de cada día),
     * del más reciente al más antiguo.
     *
     * @return list<Arqueo>
     */
    public function historialDeFisica(int $cuentaFisicaId, int $limite = 24): array;

    public function guardar(Arqueo $arqueo): Arqueo;
}
