<?php

declare(strict_types=1);

namespace src\ayuda\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\value_objects\OrigenRespuesta;
use src\ayuda\domain\value_objects\PreguntaAyuda;
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
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO ayuda_consultas
                    (identidad_id, huella, pregunta, respuesta, fuentes, origen, resuelta)
             VALUES (:identidad, :huella, :pregunta, :respuesta, :fuentes, :origen, :resuelta)'
        );
        $st->execute([
            ':identidad' => $identidadId,
            ':huella' => $huella,
            ':pregunta' => $pregunta->texto,
            ':respuesta' => $respuesta->texto,
            ':fuentes' => implode(',', $respuesta->fuentes),
            ':origen' => $respuesta->origen->value,
            ':resuelta' => $respuesta->resuelta ? 1 : 0,
        ]);
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
