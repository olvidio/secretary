<?php

declare(strict_types=1);

namespace src\apuntes\domain\contracts;

use DateTimeImmutable;
use src\apuntes\domain\entity\Apunte;

interface ApunteRepository
{
    /** @param array{cuenta?:string,concepto?:string,iniciales?:string,origen?:string,desde?:string,hasta?:string,es_cierre?:bool} $filtros */
    /** @return list<Apunte> */
    public function listar(array $filtros = []): array;

    public function porId(int $id): ?Apunte;

    public function guardar(Apunte $apunte): Apunte;

    public function borrar(int $id): void;

    public function borrarTodos(): void;

    public function borrarCierreEntre(DateTimeImmutable $desde, DateTimeImmutable $hasta): void;

    public function actualizarPar(int $id, int $parId): void;
}
