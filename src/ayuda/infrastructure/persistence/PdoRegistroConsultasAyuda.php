<?php

declare(strict_types=1);

namespace src\ayuda\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\value_objects\ComentarioPreguntaAyuda;
use src\ayuda\domain\value_objects\ConsultantePreguntaAyuda;
use src\ayuda\domain\value_objects\EstadoRepasoAyuda;
use src\ayuda\domain\value_objects\OrigenRespuesta;
use src\ayuda\domain\value_objects\PreguntaAyuda;
use src\ayuda\domain\value_objects\PreguntaAyudaPendiente;
use src\ayuda\domain\value_objects\RespuestaAyuda;

final class PdoRegistroConsultasAyuda implements RegistroConsultasAyuda
{
    private const TIENE_VOTO_NEGATIVO = 'EXISTS (SELECT 1 FROM ayuda_valoraciones v
                        WHERE v.consulta_id = c.id AND NOT v.util)';

    /** Por repasar: sin respuesta del manual, o respondida y valorada como «no me ha servido». */
    private const PENDIENTE = "c.origen = 'ia' AND c.repaso = 'nueva'
                   AND (NOT c.resuelta OR " . self::TIENE_VOTO_NEGATIVO . ')';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function buscar(string $huella): ?RespuestaAyuda
    {
        $st = $this->pdo->prepare(
            'SELECT c.respuesta, c.fuentes, c.aclaracion
               FROM ayuda_consultas c
              WHERE c.huella = :huella AND c.origen = :origen AND c.resuelta = TRUE
                AND NOT ' . self::TIENE_VOTO_NEGATIVO . '
              ORDER BY c.id DESC
              LIMIT 1'
        );
        $st->execute([':huella' => $huella, ':origen' => OrigenRespuesta::Ia->value]);
        $fila = $st->fetch();
        if (!is_array($fila)) {
            return null;
        }
        $fuentes = array_values(array_filter(explode(',', (string) $fila['fuentes'])));

        return new RespuestaAyuda(
            (string) $fila['respuesta'],
            $fuentes,
            OrigenRespuesta::Ia,
            true,
            filter_var($fila['aclaracion'], FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function guardar(
        ?int $identidadId,
        PreguntaAyuda $pregunta,
        string $huella,
        RespuestaAyuda $respuesta,
        ?string $ambitoCodigo = null,
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO ayuda_consultas
                    (identidad_id, huella, pregunta, respuesta, fuentes, origen, resuelta, ambito, aclaracion)
             VALUES (:identidad, :huella, :pregunta, :respuesta, :fuentes, :origen, :resuelta, :ambito, :aclaracion)'
        );
        $st->execute([
            ':identidad' => $identidadId,
            ':huella' => $huella,
            ':pregunta' => $pregunta->texto,
            ':respuesta' => $respuesta->texto,
            ':fuentes' => implode(',', $respuesta->fuentes),
            ':origen' => $respuesta->origen->value,
            ':resuelta' => $respuesta->resuelta ? 1 : 0,
            ':ambito' => $ambitoCodigo,
            ':aclaracion' => $respuesta->aclaracion ? 1 : 0,
        ]);
    }

    public function listarPendientes(): array
    {
        $st = $this->pdo->query(
            'WITH pend AS (
                SELECT c.pregunta, c.respuesta, c.created_at, c.ambito,
                       (SELECT COUNT(*) FROM ayuda_valoraciones v
                         WHERE v.consulta_id = c.id AND NOT v.util)::int AS negativos
                  FROM ayuda_consultas c
                 WHERE ' . self::PENDIENTE . '
             ),
             agg AS (
                SELECT pregunta,
                       COUNT(*)::int AS veces,
                       MIN(created_at) AS primera,
                       MAX(created_at) AS ultima,
                       array_remove(array_agg(DISTINCT ambito), NULL) AS ambitos,
                       SUM(negativos)::int AS negativos
                  FROM pend
                 GROUP BY pregunta
             ),
             ult AS (
                SELECT DISTINCT ON (pregunta) pregunta, respuesta
                  FROM pend
                 ORDER BY pregunta, created_at DESC
             )
             SELECT a.pregunta, a.veces, a.primera, a.ultima, a.ambitos, a.negativos,
                    u.respuesta AS ultima_respuesta
               FROM agg a
               JOIN ult u ON u.pregunta = a.pregunta
              ORDER BY a.veces DESC, a.ultima DESC'
        );
        if ($st === false) {
            return [];
        }
        $consultantes = $this->consultantesPorPregunta();
        $comentarios = $this->comentariosPorPregunta();
        $out = [];
        while ($row = $st->fetch()) {
            if (!is_array($row)) {
                continue;
            }
            $pregunta = (string) $row['pregunta'];
            $ambitos = $this->parseAmbitosPostgres($row['ambitos'] ?? null);
            $out[] = new PreguntaAyudaPendiente(
                $pregunta,
                (int) $row['veces'],
                $this->iso((string) $row['primera']),
                $this->iso((string) $row['ultima']),
                $ambitos,
                (string) ($row['ultima_respuesta'] ?? ''),
                $consultantes[$pregunta] ?? [],
                (int) $row['negativos'] > 0
                    ? PreguntaAyudaPendiente::NO_SIRVIO
                    : PreguntaAyudaPendiente::SIN_RESPUESTA,
                (int) $row['negativos'],
                $comentarios[$pregunta] ?? [],
            );
        }

        return $out;
    }

    /** @return array<string, list<ConsultantePreguntaAyuda>> */
    private function consultantesPorPregunta(): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.pregunta,
                    c.identidad_id,
                    COUNT(*)::int AS veces,
                    i.alias,
                    i.email,
                    i.nombre
               FROM ayuda_consultas c
               LEFT JOIN identidades i ON i.id = c.identidad_id
              WHERE ' . self::PENDIENTE . '
              GROUP BY c.pregunta, c.identidad_id, i.alias, i.email, i.nombre
              ORDER BY c.pregunta, veces DESC'
        );
        $st->execute();
        $map = [];
        while ($row = $st->fetch()) {
            if (!is_array($row)) {
                continue;
            }
            $pregunta = (string) $row['pregunta'];
            $id = $row['identidad_id'] !== null ? (int) $row['identidad_id'] : null;
            $map[$pregunta][] = new ConsultantePreguntaAyuda(
                $id,
                (int) $row['veces'],
                $row['alias'] !== null ? (string) $row['alias'] : null,
                $row['email'] !== null ? (string) $row['email'] : null,
                $row['nombre'] !== null ? (string) $row['nombre'] : null,
            );
        }

        return $map;
    }

    /** @return array<string, list<ComentarioPreguntaAyuda>> */
    private function comentariosPorPregunta(): array
    {
        $st = $this->pdo->query(
            'SELECT c.pregunta, v.comentario, v.created_at, v.identidad_id, i.alias, i.email, i.nombre
               FROM ayuda_valoraciones v
               JOIN ayuda_consultas c ON c.id = v.consulta_id
               LEFT JOIN identidades i ON i.id = v.identidad_id
              WHERE NOT v.util AND ' . self::PENDIENTE . '
              ORDER BY c.pregunta, v.created_at'
        );
        $map = [];
        while ($st !== false && ($row = $st->fetch())) {
            if (!is_array($row)) {
                continue;
            }
            $quien = new ConsultantePreguntaAyuda(
                $row['identidad_id'] !== null ? (int) $row['identidad_id'] : null,
                1,
                $row['alias'] !== null ? (string) $row['alias'] : null,
                $row['email'] !== null ? (string) $row['email'] : null,
                $row['nombre'] !== null ? (string) $row['nombre'] : null,
            );
            $map[(string) $row['pregunta']][] = new ComentarioPreguntaAyuda(
                (string) $row['comentario'],
                $quien->etiqueta(),
                $this->iso((string) $row['created_at']),
            );
        }

        return $map;
    }

    public function valorar(string $huella, ?int $identidadId, bool $util, string $comentario): bool
    {
        $st = $this->pdo->prepare(
            'SELECT id FROM ayuda_consultas
              WHERE huella = :huella AND origen = :origen
              ORDER BY id DESC
              LIMIT 1'
        );
        $st->execute([':huella' => $huella, ':origen' => OrigenRespuesta::Ia->value]);
        $consultaId = $st->fetchColumn();
        if ($consultaId === false) {
            return false;
        }
        $sql = 'INSERT INTO ayuda_valoraciones (consulta_id, identidad_id, util, comentario)
                VALUES (:consulta, :identidad, :util, :comentario)';
        if ($identidadId !== null) {
            $sql .= ' ON CONFLICT (consulta_id, identidad_id) WHERE identidad_id IS NOT NULL
                      DO UPDATE SET util = EXCLUDED.util, comentario = EXCLUDED.comentario, created_at = NOW()';
        }
        $this->pdo->prepare($sql)->execute([
            ':consulta' => (int) $consultaId,
            ':identidad' => $identidadId,
            ':util' => $util ? 1 : 0,
            ':comentario' => $comentario,
        ]);
        if (!$util) {
            // Una queja nueva reabre la pregunta aunque ya se hubiera repasado.
            $this->pdo->prepare(
                "UPDATE ayuda_consultas SET repaso = 'nueva', repasada_at = NULL WHERE id = :id"
            )->execute([':id' => (int) $consultaId]);
        }

        return true;
    }

    public function marcarRepasada(string $pregunta, EstadoRepasoAyuda $estado): int
    {
        $st = $this->pdo->prepare(
            'UPDATE ayuda_consultas c
                SET repaso = :estado, repasada_at = NOW()
              WHERE c.pregunta = :pregunta AND ' . self::PENDIENTE
        );
        $st->execute([':estado' => $estado->value, ':pregunta' => $pregunta]);

        return $st->rowCount();
    }

    /** @return list<string> */
    private function parseAmbitosPostgres(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }
        $texto = trim((string) $raw, '{}');
        if ($texto === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $s): string => trim($s, " \""),
            explode(',', $texto),
        )));
    }

    private function iso(string $ts): string
    {
        try {
            return (new \DateTimeImmutable($ts))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return $ts;
        }
    }

    public function consultasAlModeloDesde(?int $identidadId, DateTimeImmutable $desde): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM ayuda_consultas
              WHERE origen = :origen
                AND created_at >= :desde
                AND identidad_id IS NOT DISTINCT FROM :identidad'
        );
        $st->execute([
            ':origen' => OrigenRespuesta::Ia->value,
            ':desde' => $desde->format('Y-m-d H:i:sP'),
            ':identidad' => $identidadId,
        ]);

        return (int) $st->fetchColumn();
    }
}
