<?php

declare(strict_types=1);

namespace src\mensajes\domain\contracts;

interface MensajeRepository
{
    public function upsert(int $identidadId, string $clave, string $tipo, string $payloadJson): void;

    /**
     * Cierra los avisos abiertos de bandeja cuya clave ya no está vigente.
     *
     * @param list<string> $clavesVivas
     */
    public function cerrarSalvo(int $identidadId, array $clavesVivas): void;

    /**
     * @return list<array{id:int, tipo:string, payload:string, leido_at:?string, creado_at:string}>
     */
    public function listarAbiertos(int $identidadId): array;

    public function contarNoLeidos(int $identidadId): int;

    /** @param list<int> $ids */
    public function marcarLeidos(int $identidadId, array $ids): void;

    /** Oculta un aviso abierto; no vuelve a mostrarse mientras el contenido sea el mismo. */
    public function descartar(int $identidadId, int $id): bool;

    /** @return array{id:int, clave:string, tipo:string}|null */
    public function porId(int $identidadId, int $id): ?array;
}
