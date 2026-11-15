<?php

declare(strict_types=1);

namespace src\ayuda\domain\value_objects;

/**
 * Pregunta agregada que hay que repasar: la IA no pudo contestarla con el manual
 * o la contestó y algún usuario dijo que la respuesta no le había servido.
 */
final class PreguntaAyudaPendiente
{
    public const SIN_RESPUESTA = 'sin_respuesta';
    public const NO_SIRVIO = 'no_sirvio';

    /**
     * @param list<string> $ambitos
     * @param list<ConsultantePreguntaAyuda> $consultantes
     * @param list<ComentarioPreguntaAyuda> $comentarios
     */
    public function __construct(
        public readonly string $pregunta,
        public readonly int $veces,
        public readonly string $primeraEn,
        public readonly string $ultimaEn,
        public readonly array $ambitos,
        public readonly string $ultimaRespuesta,
        public readonly array $consultantes = [],
        public readonly string $motivo = self::SIN_RESPUESTA,
        public readonly int $votosNegativos = 0,
        public readonly array $comentarios = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'pregunta' => $this->pregunta,
            'motivo' => $this->motivo,
            'votos_negativos' => $this->votosNegativos,
            'comentarios' => array_map(
                static fn (ComentarioPreguntaAyuda $c): array => $c->toArray(),
                $this->comentarios,
            ),
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
