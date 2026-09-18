<?php

declare(strict_types=1);

namespace src\plan\domain\contracts;

interface PlanContableRepository
{
    public function idPorCodigo(string $codigo): ?int;

    /** @return array{id:int,codigo:string,nombre:string}|null */
    public function porId(int $id): ?array;

    public function codigoPorCentro(int $centroId): string;

    /** @return list<array{id:int, codigo:string, nombre:string}> */
    public function listar(): array;

    /** @return array{id:int, codigo:string, nombre:string} */
    public function guardar(?int $id, string $codigo, string $nombre): array;

    public function borrar(int $id): void;
}
