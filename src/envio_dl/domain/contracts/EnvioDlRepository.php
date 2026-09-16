<?php

declare(strict_types=1);

namespace src\envio_dl\domain\contracts;

interface EnvioDlRepository
{
    /**
     * @param list<array{persona_id:int, importe_cents:int}> $lineas
     */
    public function guardarBorrador(int $centroId, int $ejercicioId, int $totalCents, array $lineas): int;

    public function borrarBorradores(int $centroId, int $ejercicioId): void;

    /** @return array<string, mixed>|null */
    public function porId(int $id, int $centroId): ?array;

    public function marcarConfirmada(int $id): void;
}
