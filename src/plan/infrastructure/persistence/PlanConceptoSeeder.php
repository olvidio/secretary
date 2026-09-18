<?php

declare(strict_types=1);

namespace src\plan\infrastructure\persistence;

use PDO;
use src\conceptos\domain\services\CatalogoConceptos;
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
        $filas = [];
        foreach (CatalogoConceptos::todos() as $c) {
            $filas[] = $c;
        }
        foreach ($planes as $plan) {
            $planId = (int) $plan['id'];
            if ($repo->listar($planId) !== []) {
                continue;
            }
            $repo->reemplazar($planId, $filas);
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
