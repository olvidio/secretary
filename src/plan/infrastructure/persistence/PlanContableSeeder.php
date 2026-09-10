<?php

declare(strict_types=1);

namespace src\plan\infrastructure\persistence;

use PDO;
use src\plan\domain\services\CatalogoPlanesContables;

/** Siembra idempotente del plan H16n y migración desde v8. */
final class PlanContableSeeder
{
    public static function sembrar(PDO $pdo): void
    {
        $ins = $pdo->prepare(
            'INSERT INTO planes_contables (codigo, nombre) VALUES (:c, :n)
             ON CONFLICT (codigo) DO UPDATE SET nombre = excluded.nombre'
        );
        foreach (CatalogoPlanesContables::todos() as $p) {
            $ins->execute([':c' => $p['codigo'], ':n' => $p['nombre']]);
        }

        $h16nId = self::idPlan($pdo, CatalogoPlanesContables::H16N);
        if ($h16nId === null) {
            return;
        }

        $pdo->prepare('UPDATE centros SET plan_contable_id = :p')->execute([':p' => $h16nId]);
        self::backfillPartidasLegacy($pdo);
    }

    private static function backfillPartidasLegacy(PDO $pdo): void
    {
        $centros = $pdo->query('SELECT id FROM centros')->fetchAll();
        foreach ($centros as $row) {
            PdoPartidaLaboresRepository::sembrarLegacy($pdo, (int) $row['id']);
        }
    }

    private static function idPlan(PDO $pdo, string $codigo): ?int
    {
        $st = $pdo->prepare('SELECT id FROM planes_contables WHERE codigo = :c');
        $st->execute([':c' => $codigo]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
}
