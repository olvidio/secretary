<?php

declare(strict_types=1);

namespace src\ayuda\domain\value_objects;

final class RespuestaAyuda
{
    public const NO_ESTA_EN_EL_MANUAL = 'Eso no está explicado en el manual del programa. '
        . 'Pruebe a preguntarlo con otras palabras o consúltelo al secretario del centro.';

    /** @param list<string> $fuentes claves de los documentos citados */
    public function __construct(
        public readonly string $texto,
        public readonly array $fuentes,
        public readonly OrigenRespuesta $origen,
        public readonly bool $resuelta = true,
        /** La ayuda no contesta todavía: pide al usuario que concrete. */
        public readonly bool $aclaracion = false,
    ) {
    }

    public static function sinRespuesta(OrigenRespuesta $origen): self
    {
        return new self(self::NO_ESTA_EN_EL_MANUAL, [], $origen, false);
    }

    /** La misma respuesta, ya no como petición de aclaración (solo se pregunta una vez). */
    public function sinAclaracion(): self
    {
        return new self($this->texto, $this->fuentes, $this->origen, $this->resuelta, false);
    }

    public function comoCache(): self
    {
        return new self($this->texto, $this->fuentes, OrigenRespuesta::Cache, $this->resuelta, $this->aclaracion);
    }
}
