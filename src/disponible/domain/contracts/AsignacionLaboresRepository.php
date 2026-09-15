<?php

declare(strict_types=1);

namespace src\disponible\domain\contracts;

interface AsignacionLaboresRepository
{
    /**
     * @param list<array{persona_id:int, codigo_maestro:string, importe_cents:int}> $lineas
     */
    public function guardarBorrador(int $centroId, int $ejercicioId, array $lineas): int;

    public function borrarBorradores(int $centroId, int $ejercicioId): void;

    public function marcarConfirmada(int $id): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function listarDeCentro(int $centroId): array;

    /**
     * @return array<string, mixed>|null
     */
    public function porId(int $id, int $centroId): ?array;

    /**
     * @return list<array{id:int, codigo_maestro:string, pendiente_cents:int}>
     */
    public function pendientesDePersona(int $centroId, int $personaId): array;

    /**
     * @return list<array{id:int, codigo_maestro:string, pendiente_cents:int, importe_cents:int, asignacion_id:int}>
     */
    public function pendientesConfirmadosDePersona(int $centroId, int $personaId): array;

    /**
     * @param list<array{linea_id:int, importe_cents:int}> $consumos
     */
    public function registrarConsumos(int $remesaId, array $consumos): void;

    public function revertirConsumosDeRemesa(int $remesaId): void;

    /**
     * @return list<array{codigo_maestro:string, importe_cents:int, importe_es:string}>
     */
    public function instruccionesPendientesDePersona(int $centroId, int $personaId): array;

    /**
     * Gastos 7 del ejercicio por persona (debe − haber).
     *
     * @return list<array{persona_id:int, codigo_maestro:string, cents:int}>
     */
    public function realizadoLaboresPorPersona(int $ejercicioId): array;
}
