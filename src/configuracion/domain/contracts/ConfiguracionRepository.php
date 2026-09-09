<?php

declare(strict_types=1);

namespace src\configuracion\domain\contracts;

use src\configuracion\domain\entity\ConfiguracionCentro;

interface ConfiguracionRepository
{
    public function get(): ConfiguracionCentro;

    public function guardar(ConfiguracionCentro $config): void;
}
