<?php

declare(strict_types=1);

namespace src\acceso\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\entity\VinculoCentro;
use src\acceso\domain\services\PoliticaBloqueo;

final class PdoIdentidadRepository implements IdentidadRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function porId(int $id): ?Identidad
    {
        $st = $this->pdo->prepare('SELECT * FROM identidades WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function porEmailOAlias(string $identificador): ?Identidad
    {
        $identificador = trim($identificador);
        $st = $this->pdo->prepare(
            'SELECT * FROM identidades WHERE email = :e OR alias = :a LIMIT 1'
        );
        $st->execute([
            ':e' => strtolower($identificador),
            ':a' => strtolower($identificador),
        ]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function guardar(Identidad $identidad): Identidad
    {
        if ($identidad->id === null) {
            $st = $this->pdo->prepare(
                'INSERT INTO identidades (email, alias, password_hash, nombre, activo)
                 VALUES (:email, :alias, :hash, :nombre, :activo) RETURNING id'
            );
            $st->execute([
                ':email' => strtolower($identidad->email),
                ':alias' => $identidad->alias !== null ? strtolower($identidad->alias) : null,
                ':hash' => $identidad->passwordHash,
                ':nombre' => $identidad->nombre,
                ':activo' => (int) $identidad->activo,
            ]);
            $id = (int) $st->fetchColumn();
        } else {
            $st = $this->pdo->prepare(
                'UPDATE identidades SET email = :email, alias = :alias, password_hash = :hash,
                    nombre = :nombre, activo = :activo WHERE id = :id'
            );
            $st->execute([
                ':email' => strtolower($identidad->email),
                ':alias' => $identidad->alias !== null ? strtolower($identidad->alias) : null,
                ':hash' => $identidad->passwordHash,
                ':nombre' => $identidad->nombre,
                ':activo' => (int) $identidad->activo,
                ':id' => $identidad->id,
            ]);
            $id = $identidad->id;
        }

        return $this->porId($id) ?? throw new RuntimeException('Identidad no encontrada tras guardar');
    }

    public function registrarFallo(Identidad $identidad, DateTimeImmutable $ahora): void
    {
        if ($identidad->id === null) {
            return;
        }
        $intentos = $identidad->intentosFallidos + 1;
        $hasta = PoliticaBloqueo::debeBloquear($intentos)
            ? PoliticaBloqueo::bloqueadoHasta($ahora)->format('c')
            : null;
        $st = $this->pdo->prepare(
            'UPDATE identidades SET intentos_fallidos = :n, bloqueado_hasta = :h WHERE id = :id'
        );
        $st->execute([':n' => $intentos, ':h' => $hasta, ':id' => $identidad->id]);
    }

    public function registrarExito(Identidad $identidad, DateTimeImmutable $ahora): void
    {
        if ($identidad->id === null) {
            return;
        }
        $st = $this->pdo->prepare(
            'UPDATE identidades SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = :u
             WHERE id = :id'
        );
        $st->execute([':u' => $ahora->format('c'), ':id' => $identidad->id]);
    }

    public function centrosDe(int $identidadId): array
    {
        $st = $this->pdo->prepare(
            'SELECT ic.centro_id, ic.rol, c.codigo, c.nombre
             FROM identidad_centro ic
             INNER JOIN centros c ON c.id = ic.centro_id
             WHERE ic.identidad_id = :id
             ORDER BY c.nombre'
        );
        $st->execute([':id' => $identidadId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = new VinculoCentro(
                (int) $row['centro_id'],
                (string) $row['rol'],
                (string) $row['codigo'],
                (string) $row['nombre'],
            );
        }

        return $out;
    }

    public function personasDe(int $identidadId): array
    {
        $st = $this->pdo->prepare(
            'SELECT persona_id FROM identidad_persona WHERE identidad_id = :id'
        );
        $st->execute([':id' => $identidadId]);
        $ids = [];
        foreach ($st->fetchAll() as $row) {
            $ids[] = (int) $row['persona_id'];
        }

        return $ids;
    }

    public function vincularCentro(int $identidadId, int $centroId, string $rol): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO identidad_centro (identidad_id, centro_id, rol)
             VALUES (:i, :c, :r)
             ON CONFLICT (identidad_id, centro_id) DO UPDATE SET rol = excluded.rol'
        );
        $st->execute([':i' => $identidadId, ':c' => $centroId, ':r' => $rol]);
    }

    public function vincularPersona(int $identidadId, int $personaId): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO identidad_persona (identidad_id, persona_id)
             VALUES (:i, :p)
             ON CONFLICT (identidad_id, persona_id) DO NOTHING'
        );
        $st->execute([':i' => $identidadId, ':p' => $personaId]);
    }

    public function totpConfirmado(int $identidadId): bool
    {
        $st = $this->pdo->prepare(
            'SELECT confirmado_at FROM identidad_totp WHERE identidad_id = :id'
        );
        $st->execute([':id' => $identidadId]);
        $v = $st->fetchColumn();

        return $v !== false && $v !== null && $v !== '';
    }

    public function totpSecretoCifrado(int $identidadId): ?string
    {
        $st = $this->pdo->prepare(
            'SELECT secret_cifrado FROM identidad_totp WHERE identidad_id = :id'
        );
        $st->execute([':id' => $identidadId]);
        $v = $st->fetchColumn();

        return is_string($v) ? $v : null;
    }

    public function guardarTotp(int $identidadId, string $secretCifrado, ?DateTimeImmutable $confirmadoAt): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO identidad_totp (identidad_id, secret_cifrado, confirmado_at)
             VALUES (:id, :s, :c)
             ON CONFLICT (identidad_id) DO UPDATE SET
                secret_cifrado = excluded.secret_cifrado,
                confirmado_at = excluded.confirmado_at'
        );
        $st->execute([
            ':id' => $identidadId,
            ':s' => $secretCifrado,
            ':c' => $confirmadoAt?->format('c'),
        ]);
    }

    public function confirmarTotp(int $identidadId, DateTimeImmutable $cuando): void
    {
        $st = $this->pdo->prepare(
            'UPDATE identidad_totp SET confirmado_at = :c WHERE identidad_id = :id'
        );
        $st->execute([':c' => $cuando->format('c'), ':id' => $identidadId]);
    }

    public function reemplazarRecovery(int $identidadId, array $hashes): void
    {
        $this->pdo->prepare('DELETE FROM identidad_recovery WHERE identidad_id = :id')
            ->execute([':id' => $identidadId]);
        $st = $this->pdo->prepare(
            'INSERT INTO identidad_recovery (identidad_id, code_hash) VALUES (:i, :h)'
        );
        foreach ($hashes as $hash) {
            $st->execute([':i' => $identidadId, ':h' => $hash]);
        }
    }

    public function recoveryPendientes(int $identidadId): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, code_hash FROM identidad_recovery
             WHERE identidad_id = :id AND usado_at IS NULL'
        );
        $st->execute([':id' => $identidadId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = ['id' => (int) $row['id'], 'hash' => (string) $row['code_hash']];
        }

        return $out;
    }

    public function marcarRecoveryUsado(int $id, DateTimeImmutable $cuando): void
    {
        $st = $this->pdo->prepare(
            'UPDATE identidad_recovery SET usado_at = :c WHERE id = :id'
        );
        $st->execute([':c' => $cuando->format('c'), ':id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Identidad
    {
        $bloqueado = null;
        if (!empty($row['bloqueado_hasta'])) {
            $bloqueado = new DateTimeImmutable((string) $row['bloqueado_hasta']);
        }
        $ultimo = null;
        if (!empty($row['ultimo_acceso'])) {
            $ultimo = new DateTimeImmutable((string) $row['ultimo_acceso']);
        }

        return new Identidad(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (string) $row['nombre'],
            self::booleano($row['activo']),
            (int) $row['intentos_fallidos'],
            $bloqueado,
            $ultimo,
            is_string($row['alias'] ?? null) && $row['alias'] !== ''
                ? (string) $row['alias']
                : null,
        );
    }

    private static function booleano(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return (int) $v === 1;
        }
        if (is_string($v)) {
            return in_array(strtolower($v), ['1', 't', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
