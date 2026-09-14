<?php

declare(strict_types=1);

namespace src\ayuda\domain\contracts;

use DateTimeImmutable;
use src\ayuda\domain\value_objects\PreguntaAyuda;
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
    ): void;

    /** Consultas que han llegado al modelo desde esa fecha (para el límite diario). */
    public function consultasAlModeloDesde(?int $identidadId, DateTimeImmutable $desde): int;
}
