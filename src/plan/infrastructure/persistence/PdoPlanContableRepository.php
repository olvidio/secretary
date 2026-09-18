<?php

declare(strict_types=1);

namespace src\plan\infrastructure\persistence;

use InvalidArgumentException;
use PDO;
use RuntimeException;
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

    public function listar(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, codigo, nombre FROM planes_contables ORDER BY codigo'
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'codigo' => (string) $row['codigo'],
                'nombre' => (string) $row['nombre'],
            ];
        }

        return $out;
    }

    public function guardar(?int $id, string $codigo, string $nombre): array
    {
        $codigo = trim($codigo);
        $nombre = trim($nombre);
        if ($codigo === '' || $nombre === '') {
            throw new InvalidArgumentException(_("Código y nombre del plan son obligatorios"));
        }
        if (preg_match('/^[A-Za-z0-9._-]{2,16}$/', $codigo) !== 1) {
            throw new InvalidArgumentException(_("Código de plan no válido"));
        }
        $otro = $this->idPorCodigo($codigo);
        if ($otro !== null && $otro !== $id) {
            throw new InvalidArgumentException(_("Ya existe un plan con ese código"));
        }
        if ($id === null) {
            $st = $this->pdo->prepare(
                'INSERT INTO planes_contables (codigo, nombre) VALUES (:c, :n) RETURNING id'
            );
            $st->execute([':c' => $codigo, ':n' => $nombre]);
            $id = (int) $st->fetchColumn();
        } else {
            $st = $this->pdo->prepare(
                'UPDATE planes_contables SET codigo = :c, nombre = :n WHERE id = :id'
            );
            $st->execute([':c' => $codigo, ':n' => $nombre, ':id' => $id]);
        }

        return ['id' => $id, 'codigo' => $codigo, 'nombre' => $nombre];
    }

    public function borrar(int $id): void
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM centros WHERE plan_contable_id = :id');
        $st->execute([':id' => $id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new InvalidArgumentException(_("Hay centros que usan este plan; no se puede borrar"));
        }
        $del = $this->pdo->prepare('DELETE FROM planes_contables WHERE id = :id');
        $del->execute([':id' => $id]);
        if ($del->rowCount() === 0) {
            throw new RuntimeException('Plan no encontrado');
        }
    }
}
