<?php

declare(strict_types=1);

namespace src\plan\infrastructure\persistence;

use InvalidArgumentException;
use PDO;
use src\conceptos\domain\entity\Concepto;
use src\conceptos\domain\services\CatalogoConceptos;
use src\plan\domain\contracts\PlanConceptoRepository;

final class PdoPlanConceptoRepository implements PlanConceptoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(int $planId, ?string $cuenta = null): array
    {
        if ($cuenta === null) {
            $st = $this->pdo->prepare(
                'SELECT * FROM plan_conceptos WHERE plan_contable_id = :p ORDER BY cuenta, orden, codigo'
            );
            $st->execute([':p' => $planId]);
        } else {
            $st = $this->pdo->prepare(
                'SELECT * FROM plan_conceptos WHERE plan_contable_id = :p AND cuenta = :c ORDER BY orden, codigo'
            );
            $st->execute([':p' => $planId, ':c' => $cuenta]);
        }
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function reemplazar(int $planId, array $conceptos): void
    {
        if ($conceptos === []) {
            throw new InvalidArgumentException(_("El plan debe tener al menos un concepto"));
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM plan_conceptos WHERE plan_contable_id = :p')
                ->execute([':p' => $planId]);
            $ins = $this->pdo->prepare(
                'INSERT INTO plan_conceptos (plan_contable_id, codigo, cuenta, nombre, descripcion, naturaleza, orden)
                 VALUES (:p, :codigo, :cuenta, :nombre, :descripcion, :naturaleza, :orden)'
            );
            foreach ($conceptos as $c) {
                $ins->execute([
                    ':p' => $planId,
                    ':codigo' => $c['codigo'],
                    ':cuenta' => $c['cuenta'],
                    ':nombre' => $c['nombre'],
                    ':descripcion' => $c['descripcion'],
                    ':naturaleza' => $c['naturaleza'],
                    ':orden' => $c['orden'],
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function copiarDesdePlan(int $origenId, int $destinoId): void
    {
        $conceptos = $this->listar($origenId);
        if ($conceptos === []) {
            return;
        }
        $filas = [];
        foreach ($conceptos as $c) {
            $filas[] = [
                'codigo' => $c->codigo,
                'cuenta' => $c->cuenta,
                'nombre' => $c->nombre,
                'descripcion' => $c->descripcion,
                'naturaleza' => $c->naturaleza,
                'orden' => $c->orden,
            ];
        }
        $this->reemplazar($destinoId, $filas);
    }

    public function sembrarCatalogoSiVacio(int $planId): void
    {
        if ($this->listar($planId) !== []) {
            return;
        }
        $this->reemplazar($planId, CatalogoConceptos::todos());
    }

    public function planIdDeCentro(int $centroId): ?int
    {
        $st = $this->pdo->prepare('SELECT plan_contable_id FROM centros WHERE id = :c');
        $st->execute([':c' => $centroId]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Concepto
    {
        return new Concepto(
            (string) $row['codigo'],
            (string) $row['cuenta'],
            (string) $row['nombre'],
            (string) ($row['descripcion'] ?? ''),
            (string) $row['naturaleza'],
            (int) $row['orden'],
        );
    }
}
