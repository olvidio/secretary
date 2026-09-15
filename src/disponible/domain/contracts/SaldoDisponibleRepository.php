<?php

declare(strict_types=1);

namespace src\disponible\domain\contracts;

interface SaldoDisponibleRepository
{
    public function saldoDe(int $centroId, int $personaId): int;

    /**
     * @return list<array{persona_id:int, saldo_cents:int}>
     */
    public function listarDeCentro(int $centroId): array;

    public function aplicar(
        int $centroId,
        int $personaId,
        int $importeCents,
        string $fecha,
        string $origen,
        ?int $ejercicioId = null,
        ?int $remesaId = null,
        ?int $asignacionId = null,
        ?string $nota = null,
    ): int;

    public function revertirPorRemesa(int $remesaId): void;
}
