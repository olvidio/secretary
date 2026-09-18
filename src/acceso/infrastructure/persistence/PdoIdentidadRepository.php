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
use src\acceso\domain\value_objects\LayoutPantalla;

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
                'INSERT INTO identidades (email, alias, password_hash, nombre, activo, es_admin)
                 VALUES (:email, :alias, :hash, :nombre, :activo, :admin) RETURNING id'
            );
            $st->execute([
                ':email' => strtolower($identidad->email),
                ':alias' => $identidad->alias !== null ? strtolower($identidad->alias) : null,
                ':hash' => $identidad->passwordHash,
                ':nombre' => $identidad->nombre,
                ':activo' => (int) $identidad->activo,
                ':admin' => (int) $identidad->esAdmin,
            ]);
            $id = (int) $st->fetchColumn();
        } else {
            $st = $this->pdo->prepare(
                'UPDATE identidades SET email = :email, alias = :alias, password_hash = :hash,
                    nombre = :nombre, activo = :activo, es_admin = :admin WHERE id = :id'
            );
            $st->execute([
                ':email' => strtolower($identidad->email),
                ':alias' => $identidad->alias !== null ? strtolower($identidad->alias) : null,
                ':hash' => $identidad->passwordHash,
                ':nombre' => $identidad->nombre,
                ':activo' => (int) $identidad->activo,
                ':admin' => (int) $identidad->esAdmin,
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
            'SELECT persona_id FROM identidad_persona WHERE identidad_id = :id ORDER BY persona_id'
        );
        $st->execute([':id' => $identidadId]);
        $ids = [];
        foreach ($st->fetchAll() as $row) {
            $ids[] = (int) $row['persona_id'];
        }

        return $ids;
    }

    public function personasVinculoDe(int $identidadId): array
    {
        $st = $this->pdo->prepare(
            'SELECT p.id AS persona_id, p.iniciales, p.nombre, p.apellidos, p.centro_id,
                    c.nombre AS centro_nombre, c.codigo AS centro_codigo, ip.anio
             FROM identidad_persona ip
             INNER JOIN personas p ON p.id = ip.persona_id
             INNER JOIN centros c ON c.id = p.centro_id
             WHERE ip.identidad_id = :id AND p.activo = TRUE
             ORDER BY c.nombre, p.iniciales'
        );
        $st->execute([':id' => $identidadId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $nombre = trim((string) $row['nombre'] . ' ' . (string) $row['apellidos']);
            $out[] = [
                'persona_id' => (int) $row['persona_id'],
                'iniciales' => (string) $row['iniciales'],
                'nombre_completo' => $nombre,
                'centro_id' => (int) $row['centro_id'],
                'centro_nombre' => (string) $row['centro_nombre'],
                'centro_codigo' => (string) $row['centro_codigo'],
                'anio' => isset($row['anio']) && $row['anio'] !== null ? (int) $row['anio'] : null,
            ];
        }

        return $out;
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

    public function vincularPersona(int $identidadId, int $personaId, ?int $anio = null): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO identidad_persona (identidad_id, persona_id, anio)
             VALUES (:i, :p, :a)
             ON CONFLICT (identidad_id, persona_id) DO UPDATE SET anio = excluded.anio'
        );
        $st->execute([':i' => $identidadId, ':p' => $personaId, ':a' => $anio]);
    }

    public function tienePersonaEnCentro(int $identidadId, int $centroId): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM identidad_persona ip
             INNER JOIN personas p ON p.id = ip.persona_id
             WHERE ip.identidad_id = :i AND p.centro_id = :c AND p.activo = TRUE
             LIMIT 1'
        );
        $st->execute([':i' => $identidadId, ':c' => $centroId]);

        return (bool) $st->fetchColumn();
    }

    public function tienePersonaEnAlgunCentro(int $identidadId): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM identidad_persona ip
             INNER JOIN personas p ON p.id = ip.persona_id
             WHERE ip.identidad_id = :i AND p.centro_id IS NOT NULL AND p.activo = TRUE
             LIMIT 1'
        );
        $st->execute([':i' => $identidadId]);

        return (bool) $st->fetchColumn();
    }

    public function anioVinculoPersona(int $identidadId, int $personaId): ?int
    {
        $st = $this->pdo->prepare(
            'SELECT anio FROM identidad_persona WHERE identidad_id = :i AND persona_id = :p'
        );
        $st->execute([':i' => $identidadId, ':p' => $personaId]);
        $val = $st->fetchColumn();
        if ($val === false || $val === null) {
            return null;
        }

        return (int) $val;
    }

    public function desvincularPersona(int $personaId): void
    {
        $st = $this->pdo->prepare('DELETE FROM identidad_persona WHERE persona_id = :p');
        $st->execute([':p' => $personaId]);
    }

    public function identidadDePersona(int $personaId): ?Identidad
    {
        $st = $this->pdo->prepare(
            'SELECT i.* FROM identidades i
             INNER JOIN identidad_persona ip ON ip.identidad_id = i.id
             WHERE ip.persona_id = :p LIMIT 1'
        );
        $st->execute([':p' => $personaId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function usuariosDeCentro(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT i.id, i.email, i.alias, i.nombre, ic.rol
             FROM identidad_centro ic
             INNER JOIN identidades i ON i.id = ic.identidad_id
             WHERE ic.centro_id = :c
             ORDER BY i.alias NULLS LAST, i.email'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'email' => (string) $row['email'],
                'alias' => is_string($row['alias'] ?? null) && $row['alias'] !== ''
                    ? (string) $row['alias']
                    : null,
                'nombre' => (string) $row['nombre'],
                'rol' => (string) $row['rol'],
            ];
        }

        return $out;
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

    public function layoutDe(int $identidadId): string
    {
        $st = $this->pdo->prepare('SELECT layout FROM identidades WHERE id = :id');
        $st->execute([':id' => $identidadId]);
        $v = $st->fetchColumn();
        if (!is_string($v) || $v === '') {
            return LayoutPantalla::porDefecto()->valor;
        }

        return $v;
    }

    public function guardarLayout(int $identidadId, string $layout): void
    {
        $st = $this->pdo->prepare('UPDATE identidades SET layout = :l WHERE id = :id');
        $st->execute([':l' => $layout, ':id' => $identidadId]);
    }

    public function idiomaDe(int $identidadId): string
    {
        $st = $this->pdo->prepare('SELECT idioma FROM identidades WHERE id = :id');
        $st->execute([':id' => $identidadId]);
        $v = $st->fetchColumn();
        if (!is_string($v) || $v === '') {
            return 'es';
        }

        return $v;
    }

    public function guardarIdioma(int $identidadId, string $idioma): void
    {
        $st = $this->pdo->prepare('UPDATE identidades SET idioma = :i WHERE id = :id');
        $st->execute([':i' => $idioma, ':id' => $identidadId]);
    }

    public function emailVerificado(int $identidadId): bool
    {
        $st = $this->pdo->prepare('SELECT email_verificado_at FROM identidades WHERE id = :id');
        $st->execute([':id' => $identidadId]);
        $v = $st->fetchColumn();

        return $v !== false && $v !== null && $v !== '';
    }

    public function guardarVerificacionEmail(int $identidadId, string $token, DateTimeImmutable $expira): void
    {
        $st = $this->pdo->prepare(
            'UPDATE identidades
             SET email_verificacion_token = :t,
                 email_verificacion_expira = :e,
                 email_verificado_at = NULL
             WHERE id = :id'
        );
        $st->execute([
            ':t' => $token,
            ':e' => $expira->format('c'),
            ':id' => $identidadId,
        ]);
    }

    public function porTokenVerificacionEmail(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT id, email_verificacion_expira
             FROM identidades
             WHERE email_verificacion_token = :t
             LIMIT 1'
        );
        $st->execute([':t' => $token]);
        $row = $st->fetch();
        if (!is_array($row) || empty($row['email_verificacion_expira'])) {
            return null;
        }

        return [
            'identidad_id' => (int) $row['id'],
            'expira' => new DateTimeImmutable((string) $row['email_verificacion_expira']),
        ];
    }

    public function confirmarEmail(int $identidadId, DateTimeImmutable $cuando): void
    {
        $st = $this->pdo->prepare(
            'UPDATE identidades
             SET email_verificado_at = :v,
                 email_verificacion_token = NULL,
                 email_verificacion_expira = NULL
             WHERE id = :id'
        );
        $st->execute([':v' => $cuando->format('c'), ':id' => $identidadId]);
    }

    public function marcarEmailVerificado(int $identidadId, DateTimeImmutable $cuando): void
    {
        $st = $this->pdo->prepare(
            'UPDATE identidades
             SET email_verificado_at = :v,
                 email_verificacion_token = NULL,
                 email_verificacion_expira = NULL
             WHERE id = :id'
        );
        $st->execute([':v' => $cuando->format('c'), ':id' => $identidadId]);
    }

    public function tokenVerificacionDe(int $identidadId): ?string
    {
        $st = $this->pdo->prepare(
            'SELECT email_verificacion_token FROM identidades WHERE id = :id'
        );
        $st->execute([':id' => $identidadId]);
        $v = $st->fetchColumn();
        if (!is_string($v) || $v === '') {
            return null;
        }

        return $v;
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
        $emailVerificado = null;
        if (!empty($row['email_verificado_at'])) {
            $emailVerificado = new DateTimeImmutable((string) $row['email_verificado_at']);
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
            $emailVerificado,
            self::booleano($row['es_admin'] ?? false),
        );
    }

    /** @return list<array{id:int, email:string, alias:?string, nombre:string, es_admin:bool, centros:int, personas:int}> */
    public function listarTodas(): array
    {
        $rows = $this->pdo->query(
            'SELECT i.id, i.email, i.alias, i.nombre, i.es_admin,
                    (SELECT COUNT(*) FROM identidad_centro ic WHERE ic.identidad_id = i.id) AS centros,
                    (SELECT COUNT(*) FROM identidad_persona ip WHERE ip.identidad_id = i.id) AS personas
             FROM identidades i
             ORDER BY i.es_admin DESC, i.alias NULLS LAST, i.email'
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'email' => (string) $row['email'],
                'alias' => is_string($row['alias'] ?? null) && $row['alias'] !== ''
                    ? (string) $row['alias']
                    : null,
                'nombre' => (string) $row['nombre'],
                'es_admin' => self::booleano($row['es_admin'] ?? false),
                'centros' => (int) $row['centros'],
                'personas' => (int) $row['personas'],
            ];
        }

        return $out;
    }

    public function eliminar(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM identidades WHERE id = :id AND es_admin = FALSE');
        $st->execute([':id' => $id]);
        if ($st->rowCount() === 0) {
            throw new RuntimeException('No se pudo eliminar la identidad');
        }
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
