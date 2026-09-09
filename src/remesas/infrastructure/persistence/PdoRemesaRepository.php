<?php

declare(strict_types=1);

namespace src\remesas\infrastructure\persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\Remesa;
use src\remesas\domain\entity\RemesaLinea;
use src\remesas\domain\entity\SolicitudDetalle;

final class PdoRemesaRepository implements RemesaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function guardarConLineas(Remesa $remesa): Remesa
    {
        if ($remesa->id !== null) {
            throw new InvalidArgumentException('Una remesa enviada no se reescribe; se versiona');
        }
        $st = $this->pdo->prepare(
            'INSERT INTO remesas (persona_id, centro_id, ejercicio_id, anio, mes, version, estado,
                hash_contenido, enviada_at, resuelta_at, nota)
             VALUES (:persona, :centro, :ej, :anio, :mes, :ver, :estado, :hash, now(), NULL, :nota)
             RETURNING id, enviada_at'
        );
        $st->execute([
            ':persona' => $remesa->personaId,
            ':centro' => $remesa->centroId,
            ':ej' => $remesa->ejercicioId,
            ':anio' => $remesa->anio,
            ':mes' => $remesa->mes,
            ':ver' => $remesa->version,
            ':estado' => $remesa->estado,
            ':hash' => $remesa->hashContenido,
            ':nota' => $remesa->nota,
        ]);
        $row = $st->fetch();
        if (!is_array($row)) {
            throw new InvalidArgumentException('No se pudo guardar la remesa');
        }
        $id = (int) $row['id'];
        $ins = $this->pdo->prepare(
            'INSERT INTO remesa_lineas (remesa_id, codigo_maestro, importe, detalle_json)
             VALUES (:r, :cod, :imp, CAST(:det AS jsonb))'
        );
        foreach ($remesa->lineas as $linea) {
            $ins->execute([
                ':r' => $id,
                ':cod' => $linea->codigoMaestro,
                ':imp' => $linea->importeCents,
                ':det' => json_encode($linea->detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
            ]);
        }
        $guardada = $this->porId($id);
        if ($guardada === null) {
            throw new InvalidArgumentException('No se pudo releer la remesa');
        }

        return $guardada;
    }

    public function marcarEstado(int $id, string $estado, bool $resuelta = false): void
    {
        if (!in_array($estado, Remesa::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado de remesa no válido');
        }
        $sql = 'UPDATE remesas SET estado = :e, updated_at = now()';
        if ($resuelta) {
            $sql .= ', resuelta_at = now()';
        }
        $sql .= ' WHERE id = :id';
        $st = $this->pdo->prepare($sql);
        $st->execute([':e' => $estado, ':id' => $id]);
    }

    public function actualizarNota(int $id, ?string $nota): void
    {
        $st = $this->pdo->prepare('UPDATE remesas SET nota = :n, updated_at = now() WHERE id = :id');
        $st->execute([':n' => $nota, ':id' => $id]);
    }

    public function porId(int $id): ?Remesa
    {
        $st = $this->pdo->prepare('SELECT * FROM remesas WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrateRemesa($row) : null;
    }

    public function listarDePersona(int $personaId, int $ejercicioId, int $anio, int $mes): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM remesas
             WHERE persona_id = :p AND ejercicio_id = :ej AND anio = :a AND mes = :m
             ORDER BY version'
        );
        $st->execute([':p' => $personaId, ':ej' => $ejercicioId, ':a' => $anio, ':m' => $mes]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = $this->hydrateRemesa($row);
        }

        return $out;
    }

    public function listarDeCentro(int $centroId, ?string $estado = null): array
    {
        $sql = 'SELECT * FROM remesas WHERE centro_id = :c';
        $params = [':c' => $centroId];
        if ($estado !== null && $estado !== '') {
            $sql .= ' AND estado = :e';
            $params[':e'] = $estado;
        }
        $sql .= ' ORDER BY enviada_at DESC NULLS LAST, id DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = $this->hydrateRemesa($row);
        }

        return $out;
    }

    public function enviadaDe(int $personaId, int $ejercicioId, int $anio, int $mes): ?Remesa
    {
        return $this->porEstado($personaId, $ejercicioId, $anio, $mes, 'enviada');
    }

    public function aceptadaDe(int $personaId, int $ejercicioId, int $anio, int $mes): ?Remesa
    {
        return $this->porEstado($personaId, $ejercicioId, $anio, $mes, 'aceptada');
    }

    public function maxVersion(int $personaId, int $ejercicioId, int $anio, int $mes): int
    {
        $st = $this->pdo->prepare(
            'SELECT COALESCE(MAX(version), 0) FROM remesas
             WHERE persona_id = :p AND ejercicio_id = :ej AND anio = :a AND mes = :m'
        );
        $st->execute([':p' => $personaId, ':ej' => $ejercicioId, ':a' => $anio, ':m' => $mes]);

        return (int) $st->fetchColumn();
    }

    public function guardarSolicitud(SolicitudDetalle $solicitud): SolicitudDetalle
    {
        if ($solicitud->id !== null) {
            $st = $this->pdo->prepare(
                'UPDATE remesa_solicitudes_detalle
                 SET estado = :e, resuelta_at = now(), motivo = :m
                 WHERE id = :id'
            );
            $st->execute([
                ':e' => $solicitud->estado,
                ':m' => $solicitud->motivo,
                ':id' => $solicitud->id,
            ]);
            $releida = $this->solicitudPorId($solicitud->id);
            if ($releida === null) {
                throw new InvalidArgumentException('No se pudo releer la solicitud');
            }

            return $releida;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO remesa_solicitudes_detalle (remesa_linea_id, solicitada_por, estado, motivo)
             VALUES (:l, :p, :e, :m)
             RETURNING id'
        );
        $st->execute([
            ':l' => $solicitud->remesaLineaId,
            ':p' => $solicitud->solicitadaPor,
            ':e' => $solicitud->estado,
            ':m' => $solicitud->motivo,
        ]);
        $id = (int) $st->fetchColumn();
        $releida = $this->solicitudPorId($id);
        if ($releida === null) {
            throw new InvalidArgumentException('No se pudo releer la solicitud');
        }

        return $releida;
    }

    public function solicitudPorId(int $id): ?SolicitudDetalle
    {
        $st = $this->pdo->prepare($this->sqlSolicitud() . ' WHERE s.id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrateSolicitud($row) : null;
    }

    public function ultimaSolicitudDeLinea(int $lineaId): ?SolicitudDetalle
    {
        $st = $this->pdo->prepare(
            $this->sqlSolicitud() . ' WHERE s.remesa_linea_id = :l ORDER BY s.id DESC LIMIT 1'
        );
        $st->execute([':l' => $lineaId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrateSolicitud($row) : null;
    }

    public function solicitudesPendientesDePersona(int $personaId): array
    {
        $st = $this->pdo->prepare(
            $this->sqlSolicitud() . "
             WHERE r.persona_id = :p AND s.estado = 'pendiente'
               AND r.estado IN ('enviada', 'aceptada')
             ORDER BY s.solicitada_at"
        );
        $st->execute([':p' => $personaId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = $this->hydrateSolicitud($row);
        }

        return $out;
    }

    public function enTransaccion(callable $trabajo): mixed
    {
        $propia = !$this->pdo->inTransaction();
        if ($propia) {
            $this->pdo->beginTransaction();
        }
        try {
            $resultado = $trabajo();
            if ($propia) {
                $this->pdo->commit();
            }

            return $resultado;
        } catch (\Throwable $e) {
            if ($propia) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function porEstado(int $personaId, int $ejercicioId, int $anio, int $mes, string $estado): ?Remesa
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM remesas
             WHERE persona_id = :p AND ejercicio_id = :ej AND anio = :a AND mes = :m AND estado = :e
             ORDER BY version DESC LIMIT 1'
        );
        $st->execute([
            ':p' => $personaId,
            ':ej' => $ejercicioId,
            ':a' => $anio,
            ':m' => $mes,
            ':e' => $estado,
        ]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrateRemesa($row) : null;
    }

    private function sqlSolicitud(): string
    {
        return 'SELECT s.*, l.remesa_id, l.codigo_maestro, r.persona_id, r.anio, r.mes, r.version
                FROM remesa_solicitudes_detalle s
                INNER JOIN remesa_lineas l ON l.id = s.remesa_linea_id
                INNER JOIN remesas r ON r.id = l.remesa_id';
    }

    /** @param array<string, mixed> $row */
    private function hydrateRemesa(array $row): Remesa
    {
        $id = (int) $row['id'];
        $st = $this->pdo->prepare(
            'SELECT * FROM remesa_lineas WHERE remesa_id = :id ORDER BY codigo_maestro'
        );
        $st->execute([':id' => $id]);
        $lineas = [];
        foreach ($st->fetchAll() as $lineaRow) {
            $lineas[] = $this->hydrateLinea($lineaRow);
        }

        return new Remesa(
            $id,
            (int) $row['persona_id'],
            (int) $row['centro_id'],
            (int) $row['ejercicio_id'],
            (int) $row['anio'],
            (int) $row['mes'],
            (int) $row['version'],
            (string) $row['estado'],
            (string) $row['hash_contenido'],
            $this->ts($row['enviada_at'] ?? null),
            $this->ts($row['resuelta_at'] ?? null),
            isset($row['nota']) ? (string) $row['nota'] : null,
            $lineas,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateLinea(array $row): RemesaLinea
    {
        $detalle = $row['detalle_json'] ?? [];
        if (is_string($detalle)) {
            $decoded = json_decode($detalle, true);
            $detalle = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($detalle)) {
            $detalle = [];
        }
        $items = [];
        foreach ($detalle as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = [
                'codigo' => (string) ($item['codigo'] ?? ''),
                'nombre' => (string) ($item['nombre'] ?? ''),
                'cents' => (int) ($item['cents'] ?? 0),
            ];
        }

        return new RemesaLinea(
            (int) $row['id'],
            (int) $row['remesa_id'],
            (string) $row['codigo_maestro'],
            (int) $row['importe'],
            $items,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateSolicitud(array $row): SolicitudDetalle
    {
        return new SolicitudDetalle(
            (int) $row['id'],
            (int) $row['remesa_linea_id'],
            (int) $row['solicitada_por'],
            $this->ts($row['solicitada_at']) ?? new DateTimeImmutable(),
            (string) $row['estado'],
            $this->ts($row['resuelta_at'] ?? null),
            isset($row['motivo']) ? (string) $row['motivo'] : null,
            (int) $row['remesa_id'],
            (int) $row['persona_id'],
            (string) $row['codigo_maestro'],
            (int) $row['anio'],
            (int) $row['mes'],
            (int) $row['version'],
        );
    }

    private function ts(mixed $valor): ?DateTimeImmutable
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        return new DateTimeImmutable((string) $valor);
    }
}
