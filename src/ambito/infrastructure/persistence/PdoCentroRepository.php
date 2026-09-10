<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\persistence;

use PDO;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
use src\plan\domain\services\CatalogoPlanesContables;

final class PdoCentroRepository implements CentroRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(): array
    {
        $rows = $this->pdo->query(
            'SELECT c.*, COALESCE(p.codigo, \'H16n\') AS plan_contable_codigo
             FROM centros c
             LEFT JOIN planes_contables p ON p.id = c.plan_contable_id
             ORDER BY c.id'
        )->fetchAll();
        return array_map($this->hydrate(...), $rows);
    }

    public function porId(int $id): ?Centro
    {
        $st = $this->pdo->prepare(
            'SELECT c.*, COALESCE(p.codigo, \'H16n\') AS plan_contable_codigo
             FROM centros c
             LEFT JOIN planes_contables p ON p.id = c.plan_contable_id
             WHERE c.id = :id'
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function porCodigo(string $codigo): ?Centro
    {
        $st = $this->pdo->prepare(
            'SELECT c.*, COALESCE(p.codigo, \'H16n\') AS plan_contable_codigo
             FROM centros c
             LEFT JOIN planes_contables p ON p.id = c.plan_contable_id
             WHERE c.codigo = :c'
        );
        $st->execute([':c' => $codigo]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(Centro $centro, ?int $planContableId = null): Centro
    {
        if ($centro->id === null) {
            if ($planContableId === null) {
                $stPlan = $this->pdo->prepare('SELECT id FROM planes_contables WHERE codigo = :c');
                $stPlan->execute([':c' => $centro->planContableCodigo]);
                $planContableId = $stPlan->fetchColumn();
                if ($planContableId === false) {
                    throw new \RuntimeException('Plan contable no encontrado: ' . $centro->planContableCodigo);
                }
                $planContableId = (int) $planContableId;
            }
            $st = $this->pdo->prepare(
                'INSERT INTO centros (codigo, nombre, tipo_cierre, plan_contable_id, activo)
                 VALUES (:codigo, :nombre, :tipo, :plan, :activo) RETURNING id'
            );
            $st->execute([
                ':codigo' => $centro->codigo,
                ':nombre' => $centro->nombre,
                ':tipo' => $centro->tipoCierre,
                ':plan' => $planContableId,
                ':activo' => (int) $centro->activo,
            ]);
            $id = (int) $st->fetchColumn();
        } else {
            $st = $this->pdo->prepare(
                'UPDATE centros SET codigo = :codigo, nombre = :nombre, tipo_cierre = :tipo, activo = :activo
                 WHERE id = :id'
            );
            $st->execute([
                ':codigo' => $centro->codigo,
                ':nombre' => $centro->nombre,
                ':tipo' => $centro->tipoCierre,
                ':activo' => (int) $centro->activo,
                ':id' => $centro->id,
            ]);
            $id = $centro->id;
        }

        return $this->porId($id) ?? throw new \RuntimeException('Centro no encontrado tras guardar');
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Centro
    {
        return new Centro(
            (int) $row['id'],
            (string) $row['codigo'],
            (string) $row['nombre'],
            (string) $row['tipo_cierre'],
            (string) ($row['plan_contable_codigo'] ?? CatalogoPlanesContables::H16N),
            (bool) $row['activo'],
        );
    }
}
