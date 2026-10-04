<?php

declare(strict_types=1);

namespace src\apuntes\domain\value_objects;

use InvalidArgumentException;

final class PeriodicidadEntrada
{
    public const MENSUAL = 'mensual';
    public const TRIMESTRAL = 'trimestral';
    public const ANUAL = 'anual';

    private function __construct(public readonly string $valor)
    {
    }

    public static function fromString(string $raw): self
    {
        $v = strtolower(trim($raw));
        if (!in_array($v, [self::MENSUAL, self::TRIMESTRAL, self::ANUAL], true)) {
            throw new InvalidArgumentException(_("Periodicidad mensual, trimestral o anual"));
        }

        return new self($v);
    }

    public function etiqueta(): string
    {
        return match ($this->valor) {
            self::MENSUAL => _('Mensual'),
            self::TRIMESTRAL => _('Trimestral'),
            self::ANUAL => _('Anual'),
        };
    }
}
