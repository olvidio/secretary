<?php

declare(strict_types=1);

namespace src\apuntes\domain\contracts;

interface BancoCentroImportRepository
{
    public function existe(int $centroId, ?int $cuentaFisicaId, string $banco, string $huella): bool;

    public function guardar(
        int $centroId,
        ?int $cuentaFisicaId,
        string $banco,
        string $huella,
        string $fecha,
        string $importe,
        string $concepto,
    ): void;

    /**
     * @return array<string, mixed>|null
     */
    public function porId(int $centroId, int $filaId): ?array;

    public function vincularAsiento(
        int $filaId,
        int $asientoId,
        ?int $personaId,
        string $conceptoAsignado,
    ): void;

    /**
     * @param list<string> $codigosPendiente
     * @return list<array<string, mixed>>
     */
    public function deCuentas(int $centroId, array $codigosPendiente): array;

    /**
     * Filas importadas sin asiento (pendientes de categorizar).
     *
     * @return list<array<string, mixed>>
     */
    public function sinAsentar(int $centroId): array;

    /**
     * @return list<array{concepto:string, concepto_codigo:string, tipo:string}>
     */
    public function historialCategorizado(int $centroId): array;
}
