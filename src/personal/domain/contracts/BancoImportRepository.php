<?php

declare(strict_types=1);

namespace src\personal\domain\contracts;

interface BancoImportRepository
{
    public function existe(int $personaId, string $banco, string $huella): bool;

    public function guardar(
        int $personaId,
        string $banco,
        string $huella,
        int $asientoId,
        string $fecha,
        string $importe,
        string $concepto,
    ): void;

    /**
     * @return array{banco:string, huella:string, fecha:string, importe:string, concepto:string}|null
     */
    public function porAsiento(int $asientoId): ?array;

    /**
     * @param list<string> $codigos
     * @return list<array{
     *     asiento_id:int,
     *     fecha:string,
     *     importe:string,
     *     concepto:string,
     *     nota:string,
     *     banco:string,
     *     categoria_id:?int,
     *     categoria:?string,
     *     sentido:string,
     *     sugerida_id:?int
     * }>
     */
    public function deCuentas(int $personaId, array $codigos): array;

    /**
     * Movimientos de banco ya categorizados (más reciente primero).
     *
     * @return list<array{concepto:string, cuenta_id:int, tipo:string}>
     */
    public function historialCategorizado(int $personaId): array;
}
