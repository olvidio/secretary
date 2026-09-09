<?php

declare(strict_types=1);

namespace src\apuntes\infrastructure\persistence;

use PDO;
use src\apuntes\domain\contracts\PlantillaApunteRepository;
use src\apuntes\domain\entity\PlantillaApunte;
use src\apuntes\domain\value_objects\LineaPlantillaApunte;

final class PdoPlantillaApunteRepository implements PlantillaApunteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listar(int $centroId, string $cuenta): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM plantillas_apunte
             WHERE centro_id = :c AND cuenta = :cuenta AND activa = TRUE
             ORDER BY orden, lower(nombre), id'
        );
        $st->execute([':c' => $centroId, ':cuenta' => strtoupper($cuenta)]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function porId(int $centroId, int $id): ?PlantillaApunte
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM plantillas_apunte WHERE id = :id AND centro_id = :c'
        );
        $st->execute([':id' => $id, ':c' => $centroId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(PlantillaApunte $plantilla): PlantillaApunte
    {
        $this->pdo->beginTransaction();
        try {
            if ($plantilla->id === null) {
                $st = $this->pdo->prepare(
                    'INSERT INTO plantillas_apunte (centro_id, cuenta, nombre, activa, orden)
                     VALUES (:c, :cuenta, :nombre, :activa, :orden)
                     RETURNING id'
                );
                $st->execute([
                    ':c' => $plantilla->centroId,
                    ':cuenta' => strtoupper($plantilla->cuenta),
                    ':nombre' => trim($plantilla->nombre),
                    ':activa' => $plantilla->activa,
                    ':orden' => $plantilla->orden,
                ]);
                $id = (int) $st->fetchColumn();
            } else {
                $id = $plantilla->id;
                $st = $this->pdo->prepare(
                    'UPDATE plantillas_apunte
                     SET nombre = :nombre, activa = :activa, orden = :orden
                     WHERE id = :id AND centro_id = :c'
                );
                $st->execute([
                    ':nombre' => trim($plantilla->nombre),
                    ':activa' => $plantilla->activa,
                    ':orden' => $plantilla->orden,
                    ':id' => $id,
                    ':c' => $plantilla->centroId,
                ]);
                $del = $this->pdo->prepare('DELETE FROM plantilla_lineas_apunte WHERE plantilla_id = :id');
                $del->execute([':id' => $id]);
            }

            $ins = $this->pdo->prepare(
                'INSERT INTO plantilla_lineas_apunte
                    (plantilla_id, orden, cuenta, origen, concepto_codigo, observaciones, cantidad)
                 VALUES (:pid, :orden, :cuenta, :origen, :concepto, :obs, NULL)'
            );
            foreach ($plantilla->lineas as $linea) {
                $ins->execute([
                    ':pid' => $id,
                    ':orden' => $linea->orden,
                    ':cuenta' => strtoupper($linea->cuenta),
                    ':origen' => strtoupper($linea->origen),
                    ':concepto' => $linea->conceptoCodigo,
                    ':obs' => $linea->observaciones,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $guardada = $this->porId($plantilla->centroId, $id);
        if ($guardada === null) {
            throw new \RuntimeException('No se pudo recargar la plantilla guardada');
        }

        return $guardada;
    }

    public function borrar(int $centroId, int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM plantillas_apunte WHERE id = :id AND centro_id = :c');
        $st->execute([':id' => $id, ':c' => $centroId]);
    }

    public function existeNombre(int $centroId, string $cuenta, string $nombre, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT 1 FROM plantillas_apunte
                WHERE centro_id = :c AND cuenta = :cuenta AND lower(nombre) = lower(:nombre)';
        $params = [
            ':c' => $centroId,
            ':cuenta' => strtoupper($cuenta),
            ':nombre' => trim($nombre),
        ];
        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptoId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PlantillaApunte
    {
        $id = (int) $row['id'];
        $st = $this->pdo->prepare(
            'SELECT * FROM plantilla_lineas_apunte WHERE plantilla_id = :id ORDER BY orden'
        );
        $st->execute([':id' => $id]);
        $lineas = [];
        foreach ($st->fetchAll() as $l) {
            $lineas[] = new LineaPlantillaApunte(
                (string) ($l['cuenta'] ?? 'P'),
                (string) $l['origen'],
                (string) $l['concepto_codigo'],
                $l['observaciones'] !== null ? (string) $l['observaciones'] : null,
                (int) $l['orden'],
            );
        }

        return new PlantillaApunte(
            $id,
            (int) $row['centro_id'],
            (string) $row['cuenta'],
            (string) $row['nombre'],
            (bool) $row['activa'],
            (int) $row['orden'],
            $lineas,
        );
    }
}
