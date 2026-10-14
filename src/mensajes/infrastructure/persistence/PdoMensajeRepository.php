<?php

declare(strict_types=1);

namespace src\mensajes\infrastructure\persistence;

use PDO;
use src\mensajes\domain\contracts\MensajeRepository;

final class PdoMensajeRepository implements MensajeRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function upsert(int $identidadId, string $clave, string $tipo, string $payloadJson): void
    {
        $this->pdo->prepare(
            'INSERT INTO mensajes (identidad_id, clave, tipo, payload)
             VALUES (:i, :k, :t, CAST(:p AS jsonb))
             ON CONFLICT (identidad_id, clave) DO UPDATE SET
               tipo = EXCLUDED.tipo,
               descartado_manual = CASE
                 WHEN mensajes.payload IS DISTINCT FROM EXCLUDED.payload THEN FALSE
                 ELSE mensajes.descartado_manual
               END,
               leido_at = CASE
                 WHEN mensajes.payload IS DISTINCT FROM EXCLUDED.payload THEN NULL
                 WHEN mensajes.descartado_manual THEN mensajes.leido_at
                 WHEN mensajes.payload = EXCLUDED.payload AND mensajes.cerrado_at IS NULL
                   THEN mensajes.leido_at
                 ELSE NULL
               END,
               creado_at = CASE
                 WHEN mensajes.payload IS DISTINCT FROM EXCLUDED.payload THEN now()
                 WHEN mensajes.descartado_manual THEN mensajes.creado_at
                 WHEN mensajes.payload = EXCLUDED.payload AND mensajes.cerrado_at IS NULL
                   THEN mensajes.creado_at
                 ELSE now()
               END,
               payload = EXCLUDED.payload,
               cerrado_at = CASE
                 WHEN mensajes.payload IS DISTINCT FROM EXCLUDED.payload THEN NULL
                 WHEN mensajes.descartado_manual THEN mensajes.cerrado_at
                 ELSE NULL
               END'
        )->execute([
            ':i' => $identidadId,
            ':k' => $clave,
            ':t' => $tipo,
            ':p' => $payloadJson,
        ]);
    }

    public function cerrarSalvo(int $identidadId, array $clavesVivas): void
    {
        $sql = "UPDATE mensajes SET cerrado_at = now(), descartado_manual = FALSE
                WHERE identidad_id = :i AND cerrado_at IS NULL
                  AND tipo IN ('destinos_7', 'remesa_detalle')";
        $params = [':i' => $identidadId];
        if ($clavesVivas !== []) {
            $marcas = [];
            foreach (array_values($clavesVivas) as $n => $clave) {
                $ph = ':c' . $n;
                $marcas[] = $ph;
                $params[$ph] = $clave;
            }
            $sql .= ' AND clave NOT IN (' . implode(', ', $marcas) . ')';
        }
        $this->pdo->prepare($sql)->execute($params);
    }

    public function listarAbiertos(int $identidadId): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, tipo, payload::text AS payload, leido_at, creado_at
             FROM mensajes
             WHERE identidad_id = :i AND cerrado_at IS NULL
             ORDER BY creado_at DESC, id DESC'
        );
        $st->execute([':i' => $identidadId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'tipo' => (string) $row['tipo'],
                'payload' => (string) $row['payload'],
                'leido_at' => $row['leido_at'] !== null ? (string) $row['leido_at'] : null,
                'creado_at' => (string) $row['creado_at'],
            ];
        }

        return $out;
    }

    public function contarNoLeidos(int $identidadId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM mensajes
             WHERE identidad_id = :i AND cerrado_at IS NULL AND leido_at IS NULL'
        );
        $st->execute([':i' => $identidadId]);

        return (int) $st->fetchColumn();
    }

    public function marcarLeidos(int $identidadId, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn (int $id): bool => $id > 0,
        )));
        if ($ids === []) {
            return;
        }
        $marcas = [];
        $params = [':i' => $identidadId];
        foreach ($ids as $n => $id) {
            $ph = ':id' . $n;
            $marcas[] = $ph;
            $params[$ph] = $id;
        }
        $this->pdo->prepare(
            'UPDATE mensajes SET leido_at = now()
             WHERE identidad_id = :i AND leido_at IS NULL AND id IN (' . implode(', ', $marcas) . ')'
        )->execute($params);
    }

    public function descartar(int $identidadId, int $id): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE mensajes
                SET cerrado_at = now(), descartado_manual = TRUE
              WHERE id = :id AND identidad_id = :i AND cerrado_at IS NULL'
        );
        $st->execute([':id' => $id, ':i' => $identidadId]);

        return $st->rowCount() > 0;
    }

    public function porId(int $identidadId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, clave, tipo FROM mensajes WHERE id = :id AND identidad_id = :i LIMIT 1'
        );
        $st->execute([':id' => $id, ':i' => $identidadId]);
        $row = $st->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'clave' => (string) $row['clave'],
            'tipo' => (string) $row['tipo'],
        ];
    }
}
