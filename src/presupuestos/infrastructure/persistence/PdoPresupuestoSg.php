<?php

declare(strict_types=1);

namespace src\presupuestos\infrastructure\persistence;

use PDO;
use src\presupuestos\domain\contracts\PresupuestoSgRepository;
use src\presupuestos\domain\entity\LineaPresupuesto;
use src\shared\domain\value_objects\Dinero;

final class PdoPresupuestoSg implements PresupuestoSgRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(int $centroId, int $ejercicioId): array
    {
        $st = $this->pdo->prepare(
            'SELECT concepto_codigo, previsto FROM presupuesto_sg
             WHERE centro_id = :c AND ejercicio_id = :e
             ORDER BY concepto_codigo'
        );
        $st->execute([':c' => $centroId, ':e' => $ejercicioId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = new LineaPresupuesto('G', (string) $row['concepto_codigo'], new Dinero((string) $row['previsto']));
        }

        return $out;
    }

    public function guardar(int $centroId, int $ejercicioId, LineaPresupuesto $linea): void
    {
        $this->upsert($centroId, $ejercicioId, $linea->conceptoCodigo, $linea->previsto->toString());
    }

    private function upsert(int $centroId, int $ejercicioId, string $codigo, string $previsto): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO presupuesto_sg (centro_id, ejercicio_id, concepto_codigo, previsto)
             VALUES (:c, :e, :k, :p)
             ON CONFLICT (centro_id, ejercicio_id, concepto_codigo) DO UPDATE SET previsto = excluded.previsto'
        );
        $st->execute([':c' => $centroId, ':e' => $ejercicioId, ':k' => $codigo, ':p' => $previsto]);
    }
}
