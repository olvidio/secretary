<?php

declare(strict_types=1);

namespace src\personas\infrastructure\persistence;

use PDO;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

final class PdoPersonaRepository implements PersonaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(): array
    {
        $rows = $this->pdo->query('SELECT * FROM personas ORDER BY orden, id')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function porId(int $id): ?Persona
    {
        $st = $this->pdo->prepare('SELECT * FROM personas WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function porIniciales(string $iniciales): ?Persona
    {
        $st = $this->pdo->prepare('SELECT * FROM personas WHERE iniciales = :i');
        $st->execute([':i' => $iniciales]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(Persona $persona): Persona
    {
        if ($persona->id === null) {
            // centro_id se incluye sólo en el alta: si es null aquí, AmbitoSeeder lo
            // backfilleará en el siguiente db:migrate (ver comentario en Persona::centroId).
            $sql = 'INSERT INTO personas (nombre, apellidos, iniciales, mes_exento_inicio, mes_exento_fin,
                    mes_exento2_inicio, mes_exento2_fin, importe_vivienda_fijo, orden, centro_id)
                 VALUES (:n, :a, :i, :e1, :e2, :e3, :e4, :imp, :o, :cid)';
            $params = $this->params($persona);
            $params[':cid'] = $persona->centroId;
            $id = $this->insertId($sql, $params);
        } else {
            // La actualización NO toca centro_id deliberadamente: el formulario de
            // personas no lo conoce ni lo envía, y sobrescribirlo aquí lo pondría a NULL
            // en cada edición, deshaciendo el backfill de AmbitoSeeder.
            $st = $this->pdo->prepare(
                'UPDATE personas SET nombre=:n, apellidos=:a, iniciales=:i, mes_exento_inicio=:e1,
                    mes_exento_fin=:e2, mes_exento2_inicio=:e3, mes_exento2_fin=:e4,
                    importe_vivienda_fijo=:imp, orden=:o WHERE id = :id'
            );
            $params = $this->params($persona);
            $params[':id'] = $persona->id;
            $st->execute($params);
            $id = $persona->id;
        }

        $st = $this->pdo->prepare('SELECT * FROM personas WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return $this->hydrate(is_array($row) ? $row : []);
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
        $st = $this->pdo->prepare('DELETE FROM personas WHERE id = :id');
        $st->execute([':id' => $id]);
    }

    public function borrarTodos(): void
    {
        $this->pdo->exec('DELETE FROM personas');
    }

    public function sincronizarActivos(array $inicialesPresentes): array
    {
        if ($inicialesPresentes === []) {
            // Ver el comentario del contrato: una lista vacía no debe desactivar a
            // todo el mundo (probable fallo de parseo del origen, no una baja real).
            return ['activadas' => 0, 'desactivadas' => 0];
        }

        $placeholders = [];
        $params = [];
        foreach ($inicialesPresentes as $idx => $iniciales) {
            $ph = ':i' . $idx;
            $placeholders[] = $ph;
            $params[$ph] = $iniciales;
        }
        $lista = implode(', ', $placeholders);

        $activar = $this->pdo->prepare(
            "UPDATE personas SET activo = TRUE WHERE activo = FALSE AND iniciales IN ($lista)"
        );
        $activar->execute($params);
        $activadas = $activar->rowCount();

        $desactivar = $this->pdo->prepare(
            "UPDATE personas SET activo = FALSE WHERE activo = TRUE AND iniciales NOT IN ($lista)"
        );
        $desactivar->execute($params);
        $desactivadas = $desactivar->rowCount();

        return ['activadas' => $activadas, 'desactivadas' => $desactivadas];
    }

    /** @return array<string, mixed> */
    private function params(Persona $p): array
    {
        return [
            ':n' => $p->nombre,
            ':a' => $p->apellidos,
            ':i' => $p->iniciales,
            ':e1' => $p->mesExentoInicio,
            ':e2' => $p->mesExentoFin,
            ':e3' => $p->mesExento2Inicio,
            ':e4' => $p->mesExento2Fin,
            ':imp' => $p->importeViviendaFijo?->toString(),
            ':o' => $p->orden,
        ];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Persona
    {
        $fijo = null;
        if (!empty($row['importe_vivienda_fijo'])) {
            $fijo = new Dinero((string) $row['importe_vivienda_fijo']);
        }

        return new Persona(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['nombre'] ?? ''),
            (string) ($row['apellidos'] ?? ''),
            (string) ($row['iniciales'] ?? ''),
            $row['mes_exento_inicio'] !== null && $row['mes_exento_inicio'] !== '' ? (int) $row['mes_exento_inicio'] : null,
            $row['mes_exento_fin'] !== null && $row['mes_exento_fin'] !== '' ? (int) $row['mes_exento_fin'] : null,
            $row['mes_exento2_inicio'] !== null && $row['mes_exento2_inicio'] !== '' ? (int) $row['mes_exento2_inicio'] : null,
            $row['mes_exento2_fin'] !== null && $row['mes_exento2_fin'] !== '' ? (int) $row['mes_exento2_fin'] : null,
            $fijo,
            (int) ($row['orden'] ?? 0),
            isset($row['centro_id']) ? (int) $row['centro_id'] : null,
            !isset($row['activo']) || (bool) $row['activo'],
        );
    }
}
