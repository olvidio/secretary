<?php

declare(strict_types=1);

namespace src\ayuda\domain\value_objects;

use InvalidArgumentException;
use src\ayuda\domain\services\NormalizadorTexto;

final class PreguntaAyuda
{
    public const MINIMO = 5;
    public const MAXIMO = 400;
    public const SEPARADOR_SEGUIMIENTO = ' ⟶ ';

    public readonly string $texto;

    public function __construct(string $texto, int $maximo = self::MAXIMO)
    {
        $limpio = trim((string) preg_replace('/\s+/u', ' ', $texto));
        $longitud = mb_strlen($limpio);
        if ($longitud < self::MINIMO) {
            throw new InvalidArgumentException('Escriba la pregunta con algo más de detalle');
        }
        if ($longitud > $maximo) {
            throw new InvalidArgumentException('La pregunta no puede pasar de ' . self::MAXIMO . ' caracteres');
        }
        $this->texto = $limpio;
    }

    /**
     * La respuesta del usuario a una petición de aclaración, unida a la pregunta
     * de la que viene: es la consulta completa (para la huella y para el registro).
     */
    public function comoSeguimientoDe(self $anterior): self
    {
        return new self($anterior->texto . self::SEPARADOR_SEGUIMIENTO . $this->texto, 2 * self::MAXIMO + 10);
    }

    /**
     * Huella para reutilizar la respuesta de preguntas equivalentes. Incluye la
     * versión del manual: si el manual cambia, las respuestas guardadas caducan.
     */
    public function huella(string $versionManual): string
    {
        $normalizada = trim(NormalizadorTexto::plano($this->texto), " \t¿?¡!.,;:");

        return hash('sha256', $normalizada . '|' . $versionManual);
    }
}
