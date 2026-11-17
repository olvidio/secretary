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

    /**
     * Suelta las filas importadas de un asiento (asiento_id a NULL) para que
     * no se borren con él; devuelve sus ids para volver a ligarlas.
     *
     * @return list<int>
     */
    public function desligarDeAsiento(int $asientoId): array;

    /** @param list<int> $filaIds */
    public function religarAsiento(array $filaIds, int $asientoId): void;

    /**
     * Antes de borrar el asiento: sus filas del extracto quedan descartadas
     * (sin asiento, con la huella) para que reimportar no las vuelva a traer.
     */
    public function descartarDeAsiento(int $asientoId): void;

    /**
     * Filas descartadas al borrar su apunte, las más recientes primero.
     *
     * @return list<array<string, mixed>>
     */
    public function descartadas(int $centroId): array;

    /** Devuelve una fila descartada a pendientes de categorizar; false si no lo estaba. */
    public function recuperar(int $centroId, int $filaId): bool;

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
