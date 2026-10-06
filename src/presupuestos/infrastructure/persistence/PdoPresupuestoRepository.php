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

    public function listar(string $cuenta, int $ejercicioId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM presupuesto_lineas WHERE cuenta = :c AND ejercicio_id = :e'
        );
        $st->execute([':c' => $cuenta, ':e' => $ejercicioId]);
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

    public function guardar(int $ejercicioId, LineaPresupuesto $linea): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO presupuesto_lineas (ejercicio_id, cuenta, concepto_codigo, previsto)
             VALUES (:e, :c, :k, :p)
             ON CONFLICT (ejercicio_id, cuenta, concepto_codigo) DO UPDATE SET previsto = excluded.previsto'
        );
        $st->execute([
            ':e' => $ejercicioId,
            ':c' => $linea->cuenta,
            ':k' => $linea->conceptoCodigo,
            ':p' => $linea->previsto->toString(),
        ]);
    }

    public function previsto(string $cuenta, string $concepto, int $ejercicioId): string
    {
        $st = $this->pdo->prepare(
            'SELECT previsto FROM presupuesto_lineas
             WHERE cuenta = :c AND concepto_codigo = :k AND ejercicio_id = :e'
        );
        $st->execute([':c' => $cuenta, ':k' => $concepto, ':e' => $ejercicioId]);
        $v = $st->fetchColumn();

        return $v === false ? '0.00' : (string) $v;
    }

    public function borrarCuenta(string $cuenta, int $ejercicioId): void
    {
        $st = $this->pdo->prepare(
            'DELETE FROM presupuesto_lineas WHERE cuenta = :c AND ejercicio_id = :e'
        );
        $st->execute([':c' => $cuenta, ':e' => $ejercicioId]);
    }

    public function vaciarCuenta(string $cuenta): void
    {
        $st = $this->pdo->prepare('DELETE FROM presupuesto_lineas WHERE cuenta = :c');
        $st->execute([':c' => $cuenta]);
    }
}
