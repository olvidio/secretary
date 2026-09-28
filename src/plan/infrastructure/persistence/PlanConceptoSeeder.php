<?php

declare(strict_types=1);

namespace src\plan\infrastructure\persistence;

use PDO;
use src\plan\domain\services\CatalogoConceptosCentroSg;
use src\plan\domain\services\CatalogoConceptosClub;
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
            $planId = (int) $plan['id'];
            $codigo = (string) $plan['codigo'];
            if ($codigo === CatalogoPlanesContables::CLUB) {
                if ($repo->listar($planId) === []) {
                    $repo->reemplazar($planId, CatalogoConceptosClub::filas());
                }
                continue;
            }
            if ($codigo === CatalogoPlanesContables::CENTRO_SG) {
                if ($repo->listar($planId) === []) {
                    $repo->reemplazar($planId, CatalogoConceptosCentroSg::filas());
                }
                continue;
            }
            $repo->sembrarCatalogoSiVacio($planId);
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
