<?php

declare(strict_types=1);

namespace src\asientos\domain\contracts;

use DateTimeImmutable;
use src\asientos\domain\entity\Asiento;

interface AsientoRepository
{
    public function guardar(Asiento $asiento, bool $permitirEjercicioCerrado = false): Asiento;

    /** Reescribe cabecera y movimientos conservando id y número (D8). */
    public function actualizar(Asiento $asiento): Asiento;

    /** Marca `anulado_at`; no borra. Solo asientos `origen = import` (D8). */
    public function anular(int $id): void;

    /** Borra asientos de un origen (arranque sin `import_filas`). No toca el resto. */
    public function borrarPorOrigen(int $ejercicioId, string $origen): int;

    public function enlazar(int $idA, int $idB): void;

    /**
     * Guarda dos asientos y los enlaza en una transacción (D13).
     *
     * @return array{0: Asiento, 1: Asiento}
     */
    public function guardarEnlazados(Asiento $primero, Asiento $segundo, bool $primeroPermiteCerrado = false): array;

    /**
     * Mueve asientos `tipo=periodificacion` del ejercicio origen cuya fecha cae
     * en [desde, hasta] al destino (renumerando). Conserva `asiento_par_id`.
     *
     * @return list<Asiento>
     */
    public function traspasarPeriodificacion(
        int $ejercicioOrigenId,
        int $ejercicioDestinoId,
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
    ): array;

    public function porId(int $id): ?Asiento;

    public function borrar(int $id): void;

    /** Borra los asientos del centro generados al aceptar esa remesa (D6). */
    public function borrarPorRemesaId(int $remesaId): int;

    /**
     * @param array{cuenta?:string,libro?:string,desde?:string,hasta?:string,tipo?:string,iniciales?:string,concepto?:string,origen?:string,es_cierre?:bool,persona_id?:int} $filtros
     * @return list<Asiento>
     */
    public function listar(int $ejercicioId, array $filtros = []): array;

    /** @return list<Asiento> */
    public function listarPorEjercicio(int $ejercicioId, ?string $libro = null): array;

    public function borrarPorEjercicio(int $ejercicioId): void;

    public function borrarCierresEntre(int $ejercicioId, DateTimeImmutable $desde, DateTimeImmutable $hasta): void;

    /** Borra solo asientos `tipo=apertura` (no usa `borrar()` para no tocar pares de préstamo). */
    public function borrarAperturas(int $ejercicioId): void;

    /**
     * Sustituye las aperturas del ejercicio en una transacción.
     *
     * @param list<Asiento> $asientos
     * @return list<Asiento>
     */
    public function reemplazarAperturas(int $ejercicioId, array $asientos): array;

    public function contarApertura(int $ejercicioId): int;

    public function contarNoApertura(int $ejercicioId): int;

    public function hayDescuadrados(int $ejercicioId): bool;

    /**
     * Saldos por cuenta (debe − haber) a fecha de corte.
     *
     * @return list<array{id:int, libro:string, codigo:string, codigo_maestro:string, tipo:string, persona_id:?int, cuenta_fisica_id:?int, saldo_cents:int}>
     */
    public function saldosPorCuenta(
        int $centroId,
        int $ejercicioId,
        ?string $desde = null,
        ?string $hasta = null,
        ?string $libro = null,
    ): array;

    /**
     * Realizado del 613 por código de concepto (positivo como en el Excel).
     *
     * @return array<string, int> concepto codigo => cents
     */
    public function realizadoPorConcepto(
        int $centroId,
        int $ejercicioId,
        string $libro,
        string $desde,
        string $hasta,
        array $excluirTipos = [],
    ): array;

    /**
     * Agregado E37 por persona y código maestro de concepto (ingreso/gasto).
     *
     * @return array<string, array<string, int>> iniciales => codigo_maestro => cents
     */
    public function movimientosE37PorPersona(
        int $centroId,
        int $ejercicioId,
        string $desde,
        string $hasta,
    ): array;
}
