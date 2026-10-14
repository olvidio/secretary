<?php

declare(strict_types=1);

namespace src\ayuda\domain\value_objects;

/** Pregunta agregada que la IA no pudo contestar con el manual. */
final class PreguntaAyudaPendiente
{
    /**
     * @param list<string> $ambitos
     * @param list<ConsultantePreguntaAyuda> $consultantes
     */
    public function __construct(
        public readonly string $pregunta,
        public readonly int $veces,
        public readonly string $primeraEn,
        public readonly string $ultimaEn,
        public readonly array $ambitos,
        public readonly string $ultimaRespuesta,
        public readonly array $consultantes = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'pregunta' => $this->pregunta,
            'veces' => $this->veces,
            'primera_en' => $this->primeraEn,
            'ultima_en' => $this->ultimaEn,
            'ambitos' => $this->ambitos,
            'ultima_respuesta' => $this->ultimaRespuesta,
            'consultantes' => array_map(
                static fn (ConsultantePreguntaAyuda $c): array => $c->toArray(),
                $this->consultantes,
            ),
        ];
    }
}
