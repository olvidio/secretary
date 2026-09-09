<?php

declare(strict_types=1);

namespace src\apuntes\domain\contracts;

use src\apuntes\domain\entity\PlantillaApunte;

interface PlantillaApunteRepository
{
    /**
     * @return list<PlantillaApunte>
     */
    public function listar(int $centroId, string $cuenta): array;

    public function porId(int $centroId, int $id): ?PlantillaApunte;

    public function guardar(PlantillaApunte $plantilla): PlantillaApunte;

    public function borrar(int $centroId, int $id): void;

    public function existeNombre(int $centroId, string $cuenta, string $nombre, ?int $exceptoId = null): bool;
}
