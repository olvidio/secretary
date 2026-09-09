<?php

declare(strict_types=1);

namespace src\presupuestos\infrastructure\persistence;

use PDO;
use src\presupuestos\domain\contracts\PresupuestoRepository;
use src\presupuestos\domain\entity\LineaPresupuesto;
use src\shared\domain\value_objects\Dinero;

final class PdoPresupuestoRepository implements PresupuestoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(string $cuenta): array
    {
        $st = $this->pdo->prepare('SELECT * FROM presupuesto_lineas WHERE cuenta = :c');
        $st->execute([':c' => $cuenta]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = new LineaPresupuesto(
                (string) $row['cuenta'],
                (string) $row['concepto_codigo'],
                new Dinero((string) $row['previsto']),
            );
        }

        return $out;
    }

    public function guardar(LineaPresupuesto $linea): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO presupuesto_lineas (cuenta, concepto_codigo, previsto)
             VALUES (:c, :k, :p)
             ON CONFLICT (cuenta, concepto_codigo) DO UPDATE SET previsto = excluded.previsto'
        );
        $st->execute([
            ':c' => $linea->cuenta,
            ':k' => $linea->conceptoCodigo,
            ':p' => $linea->previsto->toString(),
        ]);
    }

    public function previsto(string $cuenta, string $concepto): string
    {
        $st = $this->pdo->prepare('SELECT previsto FROM presupuesto_lineas WHERE cuenta = :c AND concepto_codigo = :k');
        $st->execute([':c' => $cuenta, ':k' => $concepto]);
        $v = $st->fetchColumn();

        return $v === false ? '0.00' : (string) $v;
    }

    public function borrarCuenta(string $cuenta): void
    {
        $st = $this->pdo->prepare('DELETE FROM presupuesto_lineas WHERE cuenta = :c');
        $st->execute([':c' => $cuenta]);
    }
}
