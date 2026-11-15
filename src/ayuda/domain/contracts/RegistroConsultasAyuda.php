<?php

declare(strict_types=1);

namespace src\ayuda\domain\contracts;

use DateTimeImmutable;
use src\ayuda\domain\value_objects\EstadoRepasoAyuda;
use src\ayuda\domain\value_objects\PreguntaAyuda;
use src\ayuda\domain\value_objects\PreguntaAyudaPendiente;
use src\ayuda\domain\value_objects\RespuestaAyuda;

interface RegistroConsultasAyuda
{
    /** Respuesta ya contestada para esta misma huella, si sirve para reutilizar. */
    public function buscar(string $huella): ?RespuestaAyuda;

    public function guardar(
        ?int $identidadId,
        PreguntaAyuda $pregunta,
        string $huella,
        RespuestaAyuda $respuesta,
        ?string $ambitoCodigo = null,
    ): void;

    /** Consultas que han llegado al modelo desde esa fecha (para el límite diario). */
    public function consultasAlModeloDesde(?int $identidadId, DateTimeImmutable $desde): int;

    /**
     * Preguntas distintas por repasar: las que la IA no pudo contestar y las
     * que contestó pero alguien valoró como «no me ha servido». No salen las
     * ya repasadas.
     *
     * @return list<PreguntaAyudaPendiente>
     */
    public function listarPendientes(): array;

    /**
     * Apunta si la respuesta a esa huella le sirvió al usuario. Devuelve false
     * si no hay ninguna consulta al modelo con esa huella.
     */
    public function valorar(string $huella, ?int $identidadId, bool $util, string $comentario): bool;

    /** Cierra las consultas pendientes con ese texto de pregunta. Devuelve cuántas. */
    public function marcarRepasada(string $pregunta, EstadoRepasoAyuda $estado): int;
}
