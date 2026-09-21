<?php

declare(strict_types=1);

namespace src\legal\infrastructure\persistence;

use PDO;
use src\legal\domain\contracts\AceptacionLegalRepository;
use src\legal\domain\entity\AceptacionLegal;

final class PdoAceptacionLegalRepository implements AceptacionLegalRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function registrar(AceptacionLegal $aceptacion): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO aceptaciones_legales (
                identidad_id, canal, momento,
                condiciones_version, condiciones_hash,
                privacidad_version, privacidad_hash,
                texto_casilla, idioma, ip, user_agent,
                email, alias, centro_id, persona_id, token_hash, extra
            ) VALUES (
                :identidad, :canal, :momento,
                :cver, :chash, :pver, :phash,
                :casilla, :idioma, :ip, :ua,
                :email, :alias, :centro, :persona, :token, :extra
            )'
        );
        $extra = $aceptacion->huella->extra;
        $st->execute([
            ':identidad' => $aceptacion->identidadId,
            ':canal' => $aceptacion->canal,
            ':momento' => $aceptacion->momento->format('c'),
            ':cver' => $aceptacion->condicionesVersion,
            ':chash' => $aceptacion->condicionesHash,
            ':pver' => $aceptacion->privacidadVersion,
            ':phash' => $aceptacion->privacidadHash,
            ':casilla' => $aceptacion->textoCasilla,
            ':idioma' => $aceptacion->huella->idioma,
            ':ip' => $aceptacion->huella->ip,
            ':ua' => $aceptacion->huella->userAgent,
            ':email' => $aceptacion->huella->email,
            ':alias' => $aceptacion->huella->alias,
            ':centro' => $aceptacion->huella->centroId,
            ':persona' => $aceptacion->huella->personaId,
            ':token' => $aceptacion->huella->tokenHash,
            ':extra' => $extra === null ? null : json_encode($extra, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function buscarUsuarios(string $consulta, int $limite = 50): array
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            return [];
        }
        $limite = max(1, min(100, $limite));
        $params = [':lim' => $limite];
        $where = [];
        if (ctype_digit($consulta)) {
            $where[] = 'i.id = :id';
            $params[':id'] = (int) $consulta;
        }
        $params[':pat'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $consulta) . '%';
        $where[] = 'i.email ILIKE :pat ESCAPE \'\\\'';
        $where[] = 'i.alias ILIKE :pat ESCAPE \'\\\'';
        $sql = 'SELECT i.id, i.email, i.alias, i.nombre, i.es_admin, i.email_verificado_at,
                       COUNT(a.id) AS aceptaciones,
                       MIN(a.momento) AS primera_aceptacion,
                       MAX(a.momento) AS ultima_aceptacion
                FROM identidades i
                LEFT JOIN aceptaciones_legales a ON a.identidad_id = i.id
                WHERE (' . implode(' OR ', $where) . ')
                GROUP BY i.id
                ORDER BY i.id DESC
                LIMIT :lim';
        $st = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->execute();
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'email' => (string) $row['email'],
                'alias' => is_string($row['alias'] ?? null) && $row['alias'] !== ''
                    ? (string) $row['alias']
                    : null,
                'nombre' => (string) $row['nombre'],
                'es_admin' => self::booleano($row['es_admin'] ?? false),
                'email_verificado_at' => self::fechaIso($row['email_verificado_at'] ?? null),
                'aceptaciones' => (int) $row['aceptaciones'],
                'primera_aceptacion' => self::fechaIso($row['primera_aceptacion'] ?? null),
                'ultima_aceptacion' => self::fechaIso($row['ultima_aceptacion'] ?? null),
            ];
        }

        return $out;
    }

    public function porIdentidad(int $identidadId, ?string $email = null): array
    {
        $sql = 'SELECT a.id, a.identidad_id, a.canal, a.momento,
                       a.condiciones_version, a.condiciones_hash,
                       a.privacidad_version, a.privacidad_hash,
                       a.texto_casilla, a.idioma, a.ip, a.user_agent,
                       a.email, a.alias, a.centro_id, a.persona_id, a.token_hash, a.extra,
                       c.codigo AS centro_codigo, c.nombre AS centro_nombre,
                       p.iniciales AS persona_iniciales,
                       trim(concat_ws(\' \', p.nombre, p.apellidos)) AS persona_nombre
                FROM aceptaciones_legales a
                LEFT JOIN centros c ON c.id = a.centro_id
                LEFT JOIN personas p ON p.id = a.persona_id
                WHERE a.identidad_id = :id';
        $params = [':id' => $identidadId];
        if ($email !== null && $email !== '') {
            $sql .= ' OR (a.identidad_id IS NULL AND LOWER(a.email) = LOWER(:email))';
            $params[':email'] = $email;
        }
        $sql .= ' ORDER BY a.momento ASC, a.id ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $extra = $row['extra'] ?? null;
            if (is_string($extra)) {
                $extra = json_decode($extra, true);
            }
            $out[] = [
                'id' => (int) $row['id'],
                'identidad_id' => isset($row['identidad_id']) ? (int) $row['identidad_id'] : null,
                'canal' => (string) $row['canal'],
                'momento' => self::fechaIso($row['momento']) ?? '',
                'condiciones_version' => (string) $row['condiciones_version'],
                'condiciones_hash' => (string) $row['condiciones_hash'],
                'privacidad_version' => (string) $row['privacidad_version'],
                'privacidad_hash' => (string) $row['privacidad_hash'],
                'texto_casilla' => (string) $row['texto_casilla'],
                'idioma' => (string) $row['idioma'],
                'ip' => is_string($row['ip'] ?? null) ? (string) $row['ip'] : null,
                'user_agent' => is_string($row['user_agent'] ?? null) ? (string) $row['user_agent'] : null,
                'email' => is_string($row['email'] ?? null) ? (string) $row['email'] : null,
                'alias' => is_string($row['alias'] ?? null) ? (string) $row['alias'] : null,
                'centro_id' => isset($row['centro_id']) ? (int) $row['centro_id'] : null,
                'centro_codigo' => is_string($row['centro_codigo'] ?? null) ? (string) $row['centro_codigo'] : null,
                'centro_nombre' => is_string($row['centro_nombre'] ?? null) ? (string) $row['centro_nombre'] : null,
                'persona_id' => isset($row['persona_id']) ? (int) $row['persona_id'] : null,
                'persona_iniciales' => is_string($row['persona_iniciales'] ?? null)
                    ? (string) $row['persona_iniciales']
                    : null,
                'persona_nombre' => is_string($row['persona_nombre'] ?? null)
                    ? (string) $row['persona_nombre']
                    : null,
                'token_hash' => is_string($row['token_hash'] ?? null) ? (string) $row['token_hash'] : null,
                'extra' => is_array($extra) ? $extra : null,
            ];
        }

        return $out;
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

    private static function fechaIso(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (new \DateTimeImmutable((string) $v))->format('c');
    }
}
