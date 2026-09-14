<?php

declare(strict_types=1);

namespace src\acceso\application;

use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\value_objects\LayoutPantalla;

final class GuardarLayoutUsuario
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(int $identidadId, string $layout): string
    {
        $vo = LayoutPantalla::desde($layout);
        $this->identidades->guardarLayout($identidadId, $vo->valor);

        return $vo->valor;
    }
}
