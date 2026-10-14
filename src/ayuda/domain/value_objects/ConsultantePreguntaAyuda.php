<?php

declare(strict_types=1);

namespace src\ayuda\domain\value_objects;

/** Usuario que formuló una pregunta pendiente (puede repetirla varias veces). */
final class ConsultantePreguntaAyuda
{
    public function __construct(
        public readonly ?int $identidadId,
        public readonly int $veces,
        public readonly ?string $alias,
        public readonly ?string $email,
        public readonly ?string $nombre,
    ) {
    }

    public function etiqueta(): string
    {
        if ($this->identidadId === null) {
            return '—';
        }
        $alias = trim((string) ($this->alias ?? ''));
        $email = trim((string) ($this->email ?? ''));
        $nombre = trim((string) ($this->nombre ?? ''));
        if ($alias !== '' && $email !== '') {
            return $alias . ' · ' . $email;
        }
        if ($alias !== '') {
            return $alias;
        }
        if ($email !== '') {
            return $email;
        }
        if ($nombre !== '') {
            return $nombre;
        }

        return '#' . $this->identidadId;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'identidad_id' => $this->identidadId,
            'veces' => $this->veces,
            'alias' => $this->alias,
            'email' => $this->email,
            'nombre' => $this->nombre,
            'etiqueta' => $this->etiqueta(),
        ];
    }
}
