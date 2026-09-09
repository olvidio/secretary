<?php

declare(strict_types=1);

namespace src\conceptos\infrastructure\persistence;

use PDO;
use src\conceptos\domain\contracts\ConceptoRepository;
use src\conceptos\domain\entity\Concepto;

final class PdoConceptoRepository implements ConceptoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(?string $cuenta = null): array
    {
        if ($cuenta === null) {
            $rows = $this->pdo->query('SELECT * FROM conceptos ORDER BY cuenta, orden')->fetchAll();
        } else {
            $st = $this->pdo->prepare('SELECT * FROM conceptos WHERE cuenta = :c ORDER BY orden');
            $st->execute([':c' => $cuenta]);
            $rows = $st->fetchAll();
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function buscar(string $cuenta, string $codigo): ?Concepto
    {
        $st = $this->pdo->prepare('SELECT * FROM conceptos WHERE cuenta = :c AND codigo = :k');
        $st->execute([':c' => $cuenta, ':k' => $codigo]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(Concepto $concepto): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO conceptos (codigo, cuenta, nombre, descripcion, naturaleza, orden)
             VALUES (:codigo, :cuenta, :nombre, :descripcion, :naturaleza, :orden)
             ON CONFLICT (codigo, cuenta) DO UPDATE SET
                nombre = excluded.nombre,
                descripcion = excluded.descripcion,
                naturaleza = excluded.naturaleza,
                orden = excluded.orden'
        );
        $st->execute([
            ':codigo' => $concepto->codigo,
            ':cuenta' => $concepto->cuenta,
            ':nombre' => $concepto->nombre,
            ':descripcion' => $concepto->descripcion,
            ':naturaleza' => $concepto->naturaleza,
            ':orden' => $concepto->orden,
        ]);
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
