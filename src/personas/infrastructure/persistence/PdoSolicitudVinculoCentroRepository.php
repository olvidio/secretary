<?php

declare(strict_types=1);

namespace src\personas\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use src\personas\domain\contracts\SolicitudVinculoCentroRepository;
use src\personas\domain\entity\SolicitudVinculoCentro;

final class PdoSolicitudVinculoCentroRepository implements SolicitudVinculoCentroRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function porId(int $id): ?SolicitudVinculoCentro
    {
        $st = $this->pdo->prepare('SELECT * FROM solicitudes_vinculo_centro WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function pendientesDeCentro(int $centroId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM solicitudes_vinculo_centro
             WHERE centro_id = :c AND estado = 'pendiente'
             ORDER BY created_at, id"
        );
        $st->execute([':c' => $centroId]);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function deIdentidad(int $identidadId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM solicitudes_vinculo_centro
             WHERE identidad_id = :i
             ORDER BY created_at DESC, id DESC'
        );
        $st->execute([':i' => $identidadId]);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function pendiente(int $identidadId, int $centroId, int $anio): ?SolicitudVinculoCentro
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM solicitudes_vinculo_centro
             WHERE identidad_id = :i AND centro_id = :c AND anio = :a AND estado = 'pendiente'
             LIMIT 1"
        );
        $st->execute([':i' => $identidadId, ':c' => $centroId, ':a' => $anio]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(SolicitudVinculoCentro $solicitud): SolicitudVinculoCentro
    {
        if ($solicitud->id === null) {
            $st = $this->pdo->prepare(
                'INSERT INTO solicitudes_vinculo_centro
                    (identidad_id, centro_id, anio, estado, persona_id, mensaje)
                 VALUES (:i, :c, :a, :e, :p, :m)
                 RETURNING id, created_at'
            );
            $st->execute([
                ':i' => $solicitud->identidadId,
                ':c' => $solicitud->centroId,
                ':a' => $solicitud->anio,
                ':e' => $solicitud->estado,
                ':p' => $solicitud->personaId,
                ':m' => $solicitud->mensaje,
            ]);
            $row = $st->fetch();
            $id = (int) ($row['id'] ?? 0);
            $created = isset($row['created_at'])
                ? new DateTimeImmutable((string) $row['created_at'])
                : new DateTimeImmutable();

            return new SolicitudVinculoCentro(
                $id,
                $solicitud->identidadId,
                $solicitud->centroId,
                $solicitud->anio,
                $solicitud->estado,
                $solicitud->personaId,
                $solicitud->mensaje,
                $created,
            );
        }

        $st = $this->pdo->prepare(
            'UPDATE solicitudes_vinculo_centro
             SET estado = :e, persona_id = :p, mensaje = :m,
                 resolved_at = :ra, resolved_by = :rb
             WHERE id = :id'
        );
        $st->execute([
            ':e' => $solicitud->estado,
            ':p' => $solicitud->personaId,
            ':m' => $solicitud->mensaje,
            ':ra' => $solicitud->resolvedAt?->format('c'),
            ':rb' => $solicitud->resolvedBy,
            ':id' => $solicitud->id,
        ]);

        return $this->porId($solicitud->id) ?? $solicitud;
    }

    public function marcarResuelta(int $id, string $estado, ?int $personaId, int $resolvedBy): void
    {
        $st = $this->pdo->prepare(
            'UPDATE solicitudes_vinculo_centro
             SET estado = :e, persona_id = :p, resolved_at = NOW(), resolved_by = :rb
             WHERE id = :id'
        );
        $st->execute([
            ':e' => $estado,
            ':p' => $personaId,
            ':rb' => $resolvedBy,
            ':id' => $id,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): SolicitudVinculoCentro
    {
        return new SolicitudVinculoCentro(
            (int) $row['id'],
            (int) $row['identidad_id'],
            (int) $row['centro_id'],
            (int) $row['anio'],
            (string) $row['estado'],
            isset($row['persona_id']) && $row['persona_id'] !== null ? (int) $row['persona_id'] : null,
            is_string($row['mensaje'] ?? null) ? $row['mensaje'] : null,
            isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            isset($row['resolved_at']) && $row['resolved_at'] !== null
                ? new DateTimeImmutable((string) $row['resolved_at'])
                : null,
            isset($row['resolved_by']) && $row['resolved_by'] !== null ? (int) $row['resolved_by'] : null,
        );
    }
}
