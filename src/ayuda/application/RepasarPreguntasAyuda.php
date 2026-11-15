<?php

declare(strict_types=1);

namespace src\ayuda\application;

use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\value_objects\EstadoRepasoAyuda;

/** Cierra preguntas pendientes una vez repasado el manual (cubiertas o descartadas). */
final class RepasarPreguntasAyuda
{
    public function __construct(private readonly RegistroConsultasAyuda $registro)
    {
    }

    /** Devuelve cuántas consultas se han cerrado. */
    public function una(string $pregunta, string $estado): int
    {
        return $this->registro->marcarRepasada($pregunta, EstadoRepasoAyuda::deCierre($estado));
    }

    /** Cierra todas las pendientes de ahora mismo. Devuelve cuántas consultas. */
    public function todas(string $estado): int
    {
        $cierre = EstadoRepasoAyuda::deCierre($estado);
        $total = 0;
        foreach ($this->registro->listarPendientes() as $pendiente) {
            $total += $this->registro->marcarRepasada($pendiente->pregunta, $cierre);
        }

        return $total;
    }
}
