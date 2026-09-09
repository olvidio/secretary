<?php

declare(strict_types=1);

namespace src\acceso\domain\contracts;

use DateTimeImmutable;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\entity\VinculoCentro;

interface IdentidadRepository
{
    public function porId(int $id): ?Identidad;

    public function porEmailOAlias(string $identificador): ?Identidad;

    public function guardar(Identidad $identidad): Identidad;

    public function registrarFallo(Identidad $identidad, DateTimeImmutable $ahora): void;

    public function registrarExito(Identidad $identidad, DateTimeImmutable $ahora): void;

    /** @return list<VinculoCentro> */
    public function centrosDe(int $identidadId): array;

    /** @return list<int> */
    public function personasDe(int $identidadId): array;

    public function vincularCentro(int $identidadId, int $centroId, string $rol): void;

    public function vincularPersona(int $identidadId, int $personaId): void;

    public function totpConfirmado(int $identidadId): bool;

    public function totpSecretoCifrado(int $identidadId): ?string;

    public function guardarTotp(int $identidadId, string $secretCifrado, ?DateTimeImmutable $confirmadoAt): void;

    public function confirmarTotp(int $identidadId, DateTimeImmutable $cuando): void;

    /** @param list<string> $hashes */
    public function reemplazarRecovery(int $identidadId, array $hashes): void;

    /** @return list<array{id:int, hash:string}> */
    public function recoveryPendientes(int $identidadId): array;

    public function marcarRecoveryUsado(int $id, DateTimeImmutable $cuando): void;
}
