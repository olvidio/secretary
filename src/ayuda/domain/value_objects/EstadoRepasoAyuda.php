<?php

declare(strict_types=1);

namespace src\ayuda\domain\value_objects;

/** Qué se ha hecho con una pregunta pendiente al repasar el manual. */
enum EstadoRepasoAyuda: string
{
    case Nueva = 'nueva';
    /** El manual ya explica lo que se preguntaba. */
    case Cubierta = 'cubierta';
    /** No procede ampliar el manual (fuera de tema, pregunta sin sentido…). */
    case Descartada = 'descartada';

    /** Estados con los que se cierra una pendiente. */
    public static function deCierre(string $valor): self
    {
        $estado = self::tryFrom(strtolower(trim($valor)));
        if ($estado === null || $estado === self::Nueva) {
            throw new \InvalidArgumentException('Estado de repaso: cubierta o descartada');
        }

        return $estado;
    }
}
