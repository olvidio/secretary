<?php

declare(strict_types=1);

namespace src\asientos\domain\exceptions;

use DomainException;

final class AsientoDescuadrado extends DomainException
{
    public function __construct(int $debeCents, int $haberCents)
    {
        parent::__construct(
            sprintf('Asiento descuadrado: debe=%d céntimos, haber=%d céntimos', $debeCents, $haberCents)
        );
    }
}
