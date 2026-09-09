<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoEjercicioRepository implements EjercicioRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listarDeCentro(int $centroId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM ejercicios WHERE centro_id = :c ORDER BY fecha_inicio');
        $st->execute([':c' => $centroId]);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function porId(int $id): ?Ejercicio
    {
        $st = $this->pdo->prepare('SELECT * FROM ejercicios WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function abiertoDe(int $centroId): ?Ejercicio
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM ejercicios WHERE centro_id = :c AND estado = 'abierto'
             ORDER BY fecha_inicio DESC LIMIT 1"
        );
        $st->execute([':c' => $centroId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function deCentroEnFecha(int $centroId, DateTimeImmutable $fecha): ?Ejercicio
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM ejercicios WHERE centro_id = :c AND fecha_inicio <= :f AND fecha_fin >= :f
             LIMIT 1'
        );
        $st->execute([
            ':c' => $centroId,
            ':f' => (new ConverterDate('date', $fecha))->toPg(),
        ]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function contiguoAnterior(int $centroId, DateTimeImmutable $fechaInicio): ?Ejercicio
    {
        $finAnterior = $fechaInicio->modify('-1 day');
        $st = $this->pdo->prepare(
            'SELECT * FROM ejercicios WHERE centro_id = :c AND fecha_fin = :ff LIMIT 1'
        );
        $st->execute([
            ':c' => $centroId,
            ':ff' => (new ConverterDate('date', $finAnterior))->toPg(),
        ]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function posteriorConAnteriorId(int $ejercicioId): ?Ejercicio
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM ejercicios WHERE ejercicio_anterior_id = :id ORDER BY fecha_inicio LIMIT 1'
        );
        $st->execute([':id' => $ejercicioId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(Ejercicio $ejercicio): Ejercicio
    {
        if ($ejercicio->id === null) {
            $st = $this->pdo->prepare(
                'INSERT INTO ejercicios (centro_id, etiqueta, fecha_inicio, fecha_fin, fecha_corte, estado, ejercicio_anterior_id)
                 VALUES (:c, :et, :fi, :ff, :fc, :es, :ant) RETURNING id'
            );
            $st->execute($this->params($ejercicio));
            $id = (int) $st->fetchColumn();
        } else {
            $st = $this->pdo->prepare(
                'UPDATE ejercicios SET etiqueta = :et, fecha_inicio = :fi, fecha_fin = :ff,
                    fecha_corte = :fc, estado = :es, ejercicio_anterior_id = :ant
                 WHERE id = :id'
            );
            $params = $this->params($ejercicio);
            unset($params[':c']);
            $params[':id'] = $ejercicio->id;
            $st->execute($params);
            $id = $ejercicio->id;
        }

        return $this->porId($id) ?? throw new RuntimeException('Ejercicio no encontrado tras guardar');
    }

    /** @return array<string, mixed> */
    private function params(Ejercicio $e): array
    {
        return [
            ':c' => $e->centroId,
            ':et' => $e->etiqueta,
            ':fi' => (new ConverterDate('date', $e->fechaInicio))->toPg(),
            ':ff' => (new ConverterDate('date', $e->fechaFin))->toPg(),
            ':fc' => (new ConverterDate('date', $e->fechaCorte))->toPg(),
            ':es' => $e->estado,
            ':ant' => $e->ejercicioAnteriorId,
        ];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Ejercicio
    {
        return new Ejercicio(
            (int) $row['id'],
            (int) $row['centro_id'],
            (string) $row['etiqueta'],
            (new ConverterDate('date', $row['fecha_inicio']))->fromPg() ?? new DateTimeImmutable('1970-01-01'),
            (new ConverterDate('date', $row['fecha_fin']))->fromPg() ?? new DateTimeImmutable('1970-01-01'),
            (new ConverterDate('date', $row['fecha_corte']))->fromPg() ?? new DateTimeImmutable('1970-01-01'),
            (string) $row['estado'],
            isset($row['ejercicio_anterior_id']) ? (int) $row['ejercicio_anterior_id'] : null,
        );
    }
}
