<?php

declare(strict_types=1);

namespace src\shared\infrastructure\persistence;

use DateTimeImmutable;

/**
 * Uniformiza fechas hacia/desde PostgreSQL (columnas `date`, formato `Y-m-d`).
 */
final class ConverterDate
{
    public function __construct(
        private readonly string $type,
        private readonly mixed $valor,
    ) {
    }

    public function fromPg(): ?DateTimeImmutable
    {
        if ($this->valor === null || $this->valor === '') {
            return null;
        }
        if ($this->valor instanceof DateTimeImmutable) {
            return $this->type === 'date'
                ? $this->valor->setTime(0, 0)
                : $this->valor;
        }
        $raw = (string) $this->valor;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) !== 1) {
            return null;
        }

        return new DateTimeImmutable(substr($raw, 0, 10));
    }

    public function toPg(): ?string
    {
        if ($this->valor === null || $this->valor === '') {
            return null;
        }
        if ($this->valor instanceof DateTimeImmutable) {
            return $this->valor->format('Y-m-d');
        }
        $raw = (string) $this->valor;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) === 1) {
            return substr($raw, 0, 10);
        }

        return null;
    }
}
