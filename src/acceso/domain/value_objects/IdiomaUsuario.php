<?php

declare(strict_types=1);

namespace src\acceso\domain\value_objects;

use InvalidArgumentException;

final class IdiomaUsuario
{
    public const ES = 'es';
    public const CA = 'ca';

    public function __construct(public readonly string $valor)
    {
        if (!in_array($valor, self::todos(), true)) {
            throw new InvalidArgumentException('Idioma no válido (es o ca)');
        }
    }

    public static function porDefecto(): self
    {
        return new self(self::ES);
    }

    public static function desde(string $valor): self
    {
        $valor = strtolower(trim($valor));

        return $valor === '' ? self::porDefecto() : new self($valor);
    }

    /** @return list<string> */
    public static function todos(): array
    {
        return [self::ES, self::CA];
    }
}
