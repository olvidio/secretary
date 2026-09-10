<?php

declare(strict_types=1);

namespace src\plan\infrastructure\persistence;

use PDO;
use src\plan\domain\contracts\PlanContableRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class PdoPlanContableRepository implements PlanContableRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function idPorCodigo(string $codigo): ?int
    {
        $st = $this->pdo->prepare('SELECT id FROM planes_contables WHERE codigo = :c');
        $st->execute([':c' => $codigo]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function codigoPorCentro(int $centroId): string
    {
        $st = $this->pdo->prepare(
            'SELECT p.codigo FROM centros c
             JOIN planes_contables p ON p.id = c.plan_contable_id
             WHERE c.id = :id'
        );
        $st->execute([':id' => $centroId]);
        $codigo = $st->fetchColumn();

        return is_string($codigo) ? $codigo : CatalogoPlanesContables::H16N;
    }
}
