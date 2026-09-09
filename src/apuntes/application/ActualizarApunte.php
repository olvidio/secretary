<?php

declare(strict_types=1);

namespace src\apuntes\application;

use src\asientos\domain\value_objects\FilaApunteExcel;
use src\asientos\domain\contracts\AsientoRepository;

final class ActualizarApunte
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CrearApunte $crear,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<FilaApunteExcel>
     */
    public function ejecutar(int $id, array $datos): array
    {
        if ($this->asientos->porId($id) === null) {
            throw new \InvalidArgumentException('Apunte no encontrado');
        }
        $this->asientos->borrar($id);
        $datos['sin_espejo'] = true;

        return $this->crear->ejecutar($datos);
    }
}
