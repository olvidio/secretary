<?php

declare(strict_types=1);

namespace src\acceso\domain\value_objects;

use InvalidArgumentException;

final class LayoutPantalla
{
    public const EXCEL = 'excel';
    public const BURGER = 'burger';

    public function __construct(public readonly string $valor)
    {
        if (!in_array($valor, self::todos(), true)) {
            throw new InvalidArgumentException(_("Layout no válido (excel o burger)"));
        }
    }

    public static function porDefecto(): self
    {
        return new self(self::EXCEL);
    }

    public static function desde(string $valor): self
    {
        $valor = strtolower(trim($valor));

        return $valor === '' ? self::porDefecto() : new self($valor);
    }

    /** @return list<string> */
    public static function todos(): array
    {
        return [self::EXCEL, self::BURGER];
    }
}
