<?php

declare(strict_types=1);

namespace src\plan\infrastructure\persistence;

use PDO;
use src\plan\domain\services\CatalogoPlanesContables;

final class PlanConceptoSeeder
{
    public static function sembrar(PDO $pdo): void
    {
        $existe = $pdo->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'plan_conceptos'"
        )->fetchColumn();
        if ($existe === false) {
            return;
        }
        $repo = new PdoPlanConceptoRepository($pdo);
        $planes = $pdo->query('SELECT id, codigo FROM planes_contables')->fetchAll();
        foreach ($planes as $plan) {
            $repo->sembrarCatalogoSiVacio((int) $plan['id']);
        }
    }

    public static function idPlan(PDO $pdo, string $codigo = CatalogoPlanesContables::H16N): ?int
    {
        $st = $pdo->prepare('SELECT id FROM planes_contables WHERE codigo = :c');
        $st->execute([':c' => $codigo]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }
}
