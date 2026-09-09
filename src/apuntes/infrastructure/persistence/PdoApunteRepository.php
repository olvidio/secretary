<?php

declare(strict_types=1);

namespace src\apuntes\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use src\apuntes\domain\contracts\ApunteRepository;
use src\apuntes\domain\entity\Apunte;
use src\shared\domain\value_objects\Dinero;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoApunteRepository implements ApunteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array{cuenta?:string,concepto?:string,iniciales?:string,origen?:string,desde?:string,hasta?:string,es_cierre?:bool} $filtros
     *  @return list<Apunte> */
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT * FROM apuntes WHERE 1=1';
        $params = [];
        if (!empty($filtros['cuenta'])) {
            $sql .= ' AND cuenta = :cuenta';
            $params[':cuenta'] = $filtros['cuenta'];
        }
        if (!empty($filtros['concepto'])) {
            $sql .= ' AND concepto_codigo = :concepto';
            $params[':concepto'] = $filtros['concepto'];
        }
        if (!empty($filtros['iniciales'])) {
            $sql .= ' AND iniciales = :iniciales';
            $params[':iniciales'] = $filtros['iniciales'];
        }
        if (!empty($filtros['origen'])) {
            $sql .= ' AND origen = :origen';
            $params[':origen'] = $filtros['origen'];
        }
        if (!empty($filtros['desde'])) {
            $sql .= ' AND fecha >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND fecha <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (isset($filtros['es_cierre'])) {
            $sql .= ' AND es_cierre = :cierre';
            $params[':cierre'] = $filtros['es_cierre'] ? 1 : 0;
        }
        $sql .= ' ORDER BY fecha, id';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function porId(int $id): ?Apunte
    {
        $st = $this->pdo->prepare('SELECT * FROM apuntes WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(Apunte $apunte): Apunte
    {
        if ($apunte->id === null) {
            $sql = 'INSERT INTO apuntes (fecha, cuenta, origen, iniciales, concepto_codigo, observaciones, cantidad, es_cierre, par_id, created_at)
                 VALUES (:fecha, :cuenta, :origen, :iniciales, :concepto, :obs, :cant, :cierre, :par, :created)';
            $id = $this->insertId($sql, $this->params($apunte) + [':created' => date('c')]);
        } else {
            $st = $this->pdo->prepare(
                'UPDATE apuntes SET fecha=:fecha, cuenta=:cuenta, origen=:origen, iniciales=:iniciales,
                    concepto_codigo=:concepto, observaciones=:obs, cantidad=:cant, es_cierre=:cierre, par_id=:par
                 WHERE id = :id'
            );
            $params = $this->params($apunte);
            $params[':id'] = $apunte->id;
            $st->execute($params);
            $id = $apunte->id;
        }
        $saved = $this->porId($id);
        if ($saved === null) {
            throw new \RuntimeException('No se pudo guardar el apunte');
        }

        return $saved;
    }

    /** @param array<string, mixed> $params */
    private function insertId(string $sql, array $params): int
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $st = $this->pdo->prepare($sql . ' RETURNING id');
            $st->execute($params);
            return (int) $st->fetchColumn();
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    public function borrar(int $id): void
    {
        $actual = $this->porId($id);
        $st = $this->pdo->prepare('DELETE FROM apuntes WHERE id = :id OR par_id = :id');
        $st->execute([':id' => $id]);
        if ($actual?->parId) {
            $st2 = $this->pdo->prepare('DELETE FROM apuntes WHERE id = :p');
            $st2->execute([':p' => $actual->parId]);
        }
    }

    public function borrarTodos(): void
    {
        $this->pdo->exec('DELETE FROM apuntes');
    }

    public function borrarCierreEntre(DateTimeImmutable $desde, DateTimeImmutable $hasta): void
    {
        $st = $this->pdo->prepare('DELETE FROM apuntes WHERE es_cierre = 1 AND fecha >= :d AND fecha <= :h');
        $st->execute([
            ':d' => (new ConverterDate('date', $desde))->toPg(),
            ':h' => (new ConverterDate('date', $hasta))->toPg(),
        ]);
    }

    public function actualizarPar(int $id, int $parId): void
    {
        $st = $this->pdo->prepare('UPDATE apuntes SET par_id = :p WHERE id = :id');
        $st->execute([':p' => $parId, ':id' => $id]);
    }

    /** @return array<string, mixed> */
    private function params(Apunte $a): array
    {
        return [
            ':fecha' => (new ConverterDate('date', $a->fecha))->toPg(),
            ':cuenta' => $a->cuenta,
            ':origen' => $a->origen,
            ':iniciales' => $a->iniciales,
            ':concepto' => $a->conceptoCodigo,
            ':obs' => $a->observaciones,
            ':cant' => $a->cantidad->toString(),
            ':cierre' => $a->esCierre ? 1 : 0,
            ':par' => $a->parId,
        ];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Apunte
    {
        $fecha = (new ConverterDate('date', $row['fecha']))->fromPg();
        if ($fecha === null) {
            $fecha = new DateTimeImmutable('1970-01-01');
        }

        return new Apunte(
            (int) $row['id'],
            $fecha,
            (string) $row['cuenta'],
            (string) $row['origen'],
            $row['iniciales'] !== null && $row['iniciales'] !== '' ? (string) $row['iniciales'] : null,
            (string) $row['concepto_codigo'],
            $row['observaciones'] !== null ? (string) $row['observaciones'] : null,
            new Dinero((string) $row['cantidad']),
            (int) $row['es_cierre'] === 1,
            $row['par_id'] !== null ? (int) $row['par_id'] : null,
        );
    }
}
