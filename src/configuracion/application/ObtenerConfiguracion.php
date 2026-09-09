<?php

declare(strict_types=1);

namespace src\configuracion\application;

use src\configuracion\domain\contracts\ConfiguracionRepository;

final class ObtenerConfiguracion
{
    public function __construct(private readonly ConfiguracionRepository $repo)
    {
    }

    /** @return array<string, mixed> */
    public function ejecutar(): array
    {
        return $this->repo->get()->toArray();
    }
}
