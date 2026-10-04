<?php

declare(strict_types=1);

namespace src\apuntes\infrastructure\persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use src\apuntes\domain\contracts\EntradaPeriodicaRepository;
use src\apuntes\domain\entity\EntradaPeriodica;
use src\apuntes\domain\value_objects\PeriodicidadEntrada;
use src\shared\domain\value_objects\Dinero;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoEntradaPeriodicaRepository implements EntradaPeriodicaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM entradas_periodicas
             WHERE centro_id = :c AND activa = TRUE
             ORDER BY lower(iniciales), concepto_codigo, id'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function porId(int $centroId, int $id): ?EntradaPeriodica
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM entradas_periodicas WHERE id = :id AND centro_id = :c'
        );
        $st->execute([':id' => $id, ':c' => $centroId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(EntradaPeriodica $entrada): EntradaPeriodica
    {
        if ($entrada->id === null) {
            $st = $this->pdo->prepare(
                'INSERT INTO entradas_periodicas
                    (centro_id, iniciales, concepto_codigo, observaciones, cantidad,
                     periodicidad, fecha_ancla, activa)
                 VALUES (:c, :ini, :concepto, :obs, :cant, :per, :fecha, :activa)
                 RETURNING id'
            );
            $st->execute([
                ':c' => $entrada->centroId,
                ':ini' => $entrada->iniciales,
                ':concepto' => $entrada->conceptoCodigo,
                ':obs' => $entrada->observaciones,
                ':cant' => $entrada->cantidad->toString(),
                ':per' => $entrada->periodicidad->valor,
                ':fecha' => (new ConverterDate('date', $entrada->fechaAncla))->toPg(),
                ':activa' => $entrada->activa,
            ]);
            $id = (int) $st->fetchColumn();
        } else {
            $id = $entrada->id;
            $st = $this->pdo->prepare(
                'UPDATE entradas_periodicas
                 SET iniciales = :ini, concepto_codigo = :concepto, observaciones = :obs,
                     cantidad = :cant, periodicidad = :per, fecha_ancla = :fecha,
                     activa = :activa, updated_at = NOW()
                 WHERE id = :id AND centro_id = :c'
            );
            $st->execute([
                ':ini' => $entrada->iniciales,
                ':concepto' => $entrada->conceptoCodigo,
                ':obs' => $entrada->observaciones,
                ':cant' => $entrada->cantidad->toString(),
                ':per' => $entrada->periodicidad->valor,
                ':fecha' => (new ConverterDate('date', $entrada->fechaAncla))->toPg(),
                ':activa' => $entrada->activa,
                ':id' => $id,
                ':c' => $entrada->centroId,
            ]);
            if ($st->rowCount() === 0) {
                throw new InvalidArgumentException(_('Entrada periódica no encontrada'));
            }
        }

        $guardada = $this->porId($entrada->centroId, $id);
        if ($guardada === null) {
            throw new InvalidArgumentException(_('Entrada periódica no encontrada'));
        }

        return $guardada;
    }

    public function borrar(int $centroId, int $id): void
    {
        $st = $this->pdo->prepare(
            'DELETE FROM entradas_periodicas WHERE id = :id AND centro_id = :c'
        );
        $st->execute([':id' => $id, ':c' => $centroId]);
        if ($st->rowCount() === 0) {
            throw new InvalidArgumentException(_('Entrada periódica no encontrada'));
        }
    }

    public function fechasEjecutadas(int $centroId, int $entradaId): array
    {
        $st = $this->pdo->prepare(
            'SELECT e.fecha
             FROM entradas_periodicas_ejecucion e
             INNER JOIN entradas_periodicas p ON p.id = e.entrada_periodica_id
             WHERE p.centro_id = :c AND e.entrada_periodica_id = :id
             ORDER BY e.fecha'
        );
        $st->execute([':c' => $centroId, ':id' => $entradaId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $f = (new ConverterDate('date', $row['fecha']))->fromPg();
            if ($f !== null) {
                $out[] = $f->format('Y-m-d');
            }
        }

        return $out;
    }

    public function registrarEjecucion(int $entradaId, string $fechaYmd): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO entradas_periodicas_ejecucion (entrada_periodica_id, fecha)
             VALUES (:id, :fecha)
             ON CONFLICT (entrada_periodica_id, fecha) DO NOTHING'
        );
        $st->execute([
            ':id' => $entradaId,
            ':fecha' => $fechaYmd,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): EntradaPeriodica
    {
        $fecha = (new ConverterDate('date', $row['fecha_ancla']))->fromPg();
        if ($fecha === null) {
            throw new InvalidArgumentException(_('Fecha de ancla no válida'));
        }

        return new EntradaPeriodica(
            (int) $row['id'],
            (int) $row['centro_id'],
            (string) $row['iniciales'],
            (string) $row['concepto_codigo'],
            isset($row['observaciones']) && $row['observaciones'] !== null && $row['observaciones'] !== ''
                ? (string) $row['observaciones']
                : null,
            new Dinero((string) $row['cantidad']),
            PeriodicidadEntrada::fromString((string) $row['periodicidad']),
            $fecha,
            (bool) $row['activa'],
        );
    }
}
