<?php

declare(strict_types=1);

namespace src\shared\domain\exceptions;

use RuntimeException;

final class LimiteCopiasAlcanzado extends RuntimeException
{
    public const CODIGO = 'limite_copias';

    public function __construct()
    {
        parent::__construct(_('Solo se permite tener 5 copias en el servidor'));
    }
}
