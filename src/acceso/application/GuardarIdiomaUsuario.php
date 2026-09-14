<?php

declare(strict_types=1);

namespace src\acceso\application;

use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\value_objects\IdiomaUsuario;

final class GuardarIdiomaUsuario
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(int $identidadId, string $idioma): string
    {
        $vo = IdiomaUsuario::desde($idioma);
        $this->identidades->guardarIdioma($identidadId, $vo->valor);

        return $vo->valor;
    }
}
