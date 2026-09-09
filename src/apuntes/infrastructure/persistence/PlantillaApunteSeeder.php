<?php

declare(strict_types=1);

namespace src\apuntes\infrastructure\persistence;

use PDO;
use src\apuntes\domain\entity\PlantillaApunte;
use src\apuntes\domain\value_objects\LineaPlantillaApunte;

/** Ejemplos idempotentes de plantillas recurrentes (club, etc.). */
final class PlantillaApunteSeeder
{
    public static function sembrar(PDO $pdo): void
    {
        $existe = $pdo->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'plantillas_apunte'"
        )->fetchColumn();
        if ($existe === false) {
            return;
        }

        $repo = new PdoPlantillaApunteRepository($pdo);
        $centros = $pdo->query('SELECT id FROM centros ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($centros as $centroId) {
            self::club($repo, (int) $centroId);
        }
    }

    private static function club(PdoPlantillaApunteRepository $repo, int $centroId): void
    {
        if ($repo->existeNombre($centroId, 'P', 'Club')) {
            return;
        }

        $repo->guardar(new PlantillaApunte(
            null,
            $centroId,
            'P',
            'Club',
            true,
            10,
            [
                new LineaPlantillaApunte('P', 'A', '21', 'per el club', 0),
                new LineaPlantillaApunte('P', 'A', '111', null, 1),
                new LineaPlantillaApunte('G', 'A', '11', 'per el club', 2),
                new LineaPlantillaApunte('G', 'A', '211', 'ingres al club', 3),
            ],
        ));
    }
}
