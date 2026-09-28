<?php

declare(strict_types=1);

namespace src\presupuestos\infrastructure\persistence;

use PDO;
use src\presupuestos\domain\contracts\PresupuestoSgRepository;
use src\presupuestos\domain\entity\LineaPresupuesto;
use src\shared\domain\value_objects\Dinero;

final class PdoPresupuestoSg implements PresupuestoSgRepository
{
    private const NUM_S = 'NUM_S';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT concepto_codigo, previsto FROM presupuesto_sg
             WHERE centro_id = :c AND concepto_codigo <> :num
             ORDER BY concepto_codigo'
        );
        $st->execute([':c' => $centroId, ':num' => self::NUM_S]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = new LineaPresupuesto('G', (string) $row['concepto_codigo'], new Dinero((string) $row['previsto']));
        }

        return $out;
    }

    public function guardar(int $centroId, LineaPresupuesto $linea): void
    {
        if ($linea->conceptoCodigo === self::NUM_S) {
            return;
        }
        $this->upsert($centroId, $linea->conceptoCodigo, $linea->previsto->toString());
    }

    public function numS(int $centroId): int
    {
        $st = $this->pdo->prepare(
            'SELECT previsto FROM presupuesto_sg WHERE centro_id = :c AND concepto_codigo = :k'
        );
        $st->execute([':c' => $centroId, ':k' => self::NUM_S]);
        $v = $st->fetchColumn();

        return $v === false ? 0 : max(0, (int) $v);
    }

    public function guardarNumS(int $centroId, int $numS): void
    {
        $this->upsert($centroId, self::NUM_S, (string) max(0, $numS));
    }

    private function upsert(int $centroId, string $codigo, string $previsto): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO presupuesto_sg (centro_id, concepto_codigo, previsto)
             VALUES (:c, :k, :p)
             ON CONFLICT (centro_id, concepto_codigo) DO UPDATE SET previsto = excluded.previsto'
        );
        $st->execute([':c' => $centroId, ':k' => $codigo, ':p' => $previsto]);
    }
}
