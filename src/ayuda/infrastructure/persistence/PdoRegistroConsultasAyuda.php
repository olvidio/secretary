<?php

declare(strict_types=1);

namespace src\ayuda\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\value_objects\ConsultantePreguntaAyuda;
use src\ayuda\domain\value_objects\OrigenRespuesta;
use src\ayuda\domain\value_objects\PreguntaAyuda;
use src\ayuda\domain\value_objects\PreguntaAyudaPendiente;
use src\ayuda\domain\value_objects\RespuestaAyuda;

final class PdoRegistroConsultasAyuda implements RegistroConsultasAyuda
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function buscar(string $huella): ?RespuestaAyuda
    {
        $st = $this->pdo->prepare(
            'SELECT respuesta, fuentes
               FROM ayuda_consultas
              WHERE huella = :huella AND origen = :origen AND resuelta = TRUE
              ORDER BY id DESC
              LIMIT 1'
        );
        $st->execute([':huella' => $huella, ':origen' => OrigenRespuesta::Ia->value]);
        $fila = $st->fetch();
        if (!is_array($fila)) {
            return null;
        }
        $fuentes = array_values(array_filter(explode(',', (string) $fila['fuentes'])));

        return new RespuestaAyuda((string) $fila['respuesta'], $fuentes, OrigenRespuesta::Ia);
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
                    (identidad_id, huella, pregunta, respuesta, fuentes, origen, resuelta, ambito)
             VALUES (:identidad, :huella, :pregunta, :respuesta, :fuentes, :origen, :resuelta, :ambito)'
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
        ]);
    }

    public function listarPendientes(): array
    {
        $st = $this->pdo->query(
            "WITH agg AS (
                SELECT pregunta,
                       COUNT(*)::int AS veces,
                       MIN(created_at) AS primera,
                       MAX(created_at) AS ultima,
                       array_remove(array_agg(DISTINCT ambito), NULL) AS ambitos
                  FROM ayuda_consultas
                 WHERE NOT resuelta AND origen = 'ia'
                 GROUP BY pregunta
             ),
             ult AS (
                SELECT DISTINCT ON (pregunta) pregunta, respuesta
                  FROM ayuda_consultas
                 WHERE NOT resuelta AND origen = 'ia'
                 ORDER BY pregunta, created_at DESC
             )
             SELECT a.pregunta, a.veces, a.primera, a.ultima, a.ambitos, u.respuesta AS ultima_respuesta
               FROM agg a
               JOIN ult u ON u.pregunta = a.pregunta
              ORDER BY a.veces DESC, a.ultima DESC"
        );
        if ($st === false) {
            return [];
        }
        $consultantes = $this->consultantesPorPregunta();
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
              WHERE NOT c.resuelta AND c.origen = :origen
              GROUP BY c.pregunta, c.identidad_id, i.alias, i.email, i.nombre
              ORDER BY c.pregunta, veces DESC'
        );
        $st->execute([':origen' => OrigenRespuesta::Ia->value]);
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
