<?php

declare(strict_types=1);

namespace src\legal\domain\contracts;

use src\legal\domain\entity\AceptacionLegal;

interface AceptacionLegalRepository
{
    public function registrar(AceptacionLegal $aceptacion): void;

    /**
     * Usuarios cuyo correo, alias o id coinciden con la consulta.
     *
     * @return list<array{
     *     id: int,
     *     email: string,
     *     alias: ?string,
     *     nombre: string,
     *     es_admin: bool,
     *     email_verificado_at: ?string,
     *     aceptaciones: int,
     *     primera_aceptacion: ?string,
     *     ultima_aceptacion: ?string
     * }>
     */
    public function buscarUsuarios(string $consulta, int $limite = 50): array;

    /**
     * @return list<array{
     *     id: int,
     *     identidad_id: ?int,
     *     canal: string,
     *     momento: string,
     *     condiciones_version: string,
     *     condiciones_hash: string,
     *     privacidad_version: string,
     *     privacidad_hash: string,
     *     texto_casilla: string,
     *     idioma: string,
     *     ip: ?string,
     *     user_agent: ?string,
     *     email: ?string,
     *     alias: ?string,
     *     centro_id: ?int,
     *     centro_codigo: ?string,
     *     centro_nombre: ?string,
     *     persona_id: ?int,
     *     persona_iniciales: ?string,
     *     persona_nombre: ?string,
     *     token_hash: ?string,
     *     extra: ?array<string, mixed>
     * }>
     */
    public function porIdentidad(int $identidadId, ?string $email = null): array;
}
