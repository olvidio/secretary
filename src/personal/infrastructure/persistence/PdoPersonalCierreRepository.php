<?php

declare(strict_types=1);

namespace src\personal\infrastructure\persistence;

use DateTimeImmutable;
use PDO;
use src\personal\domain\contracts\PersonalCierreRepository;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoPersonalCierreRepository implements PersonalCierreRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function defectoDe(int $personaId): array
    {
        $st = $this->pdo->prepare(
            'SELECT dia_cierre, cierre_dia_habil FROM personas WHERE id = :id'
        );
        $st->execute([':id' => $personaId]);
        $row = $st->fetch();
        if (!is_array($row)) {
            return ['dia_cierre' => null, 'dia_habil' => false];
        }

        return [
            'dia_cierre' => $row['dia_cierre'] !== null ? (int) $row['dia_cierre'] : null,
            'dia_habil' => (bool) $row['cierre_dia_habil'],
        ];
    }

    public function guardarDefecto(int $personaId, ?int $diaCierre, bool $diaHabil): void
    {
        $st = $this->pdo->prepare(
            'UPDATE personas SET dia_cierre = :dia, cierre_dia_habil = :h WHERE id = :id'
        );
        $st->execute([
            ':dia' => $diaCierre,
            ':h' => $diaHabil ? 1 : 0,
            ':id' => $personaId,
        ]);
    }

    public function fechaMes(int $personaId, int $anio, int $mes): ?DateTimeImmutable
    {
        $st = $this->pdo->prepare(
            'SELECT fecha_cierre FROM personal_cierre_mes
             WHERE persona_id = :p AND anio = :a AND mes = :m'
        );
        $st->execute([':p' => $personaId, ':a' => $anio, ':m' => $mes]);
        $row = $st->fetch();
        if (!is_array($row)) {
            return null;
        }

        return (new ConverterDate('date', $row['fecha_cierre']))->fromPg();
    }

    public function guardarMes(int $personaId, int $anio, int $mes, DateTimeImmutable $fecha): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO personal_cierre_mes (persona_id, anio, mes, fecha_cierre)
             VALUES (:p, :a, :m, :f)
             ON CONFLICT (persona_id, anio, mes) DO UPDATE SET fecha_cierre = EXCLUDED.fecha_cierre'
        );
        $st->execute([
            ':p' => $personaId,
            ':a' => $anio,
            ':m' => $mes,
            ':f' => (new ConverterDate('date', $fecha))->toPg(),
        ]);
    }

    public function borrarMes(int $personaId, int $anio, int $mes): void
    {
        $st = $this->pdo->prepare(
            'DELETE FROM personal_cierre_mes WHERE persona_id = :p AND anio = :a AND mes = :m'
        );
        $st->execute([':p' => $personaId, ':a' => $anio, ':m' => $mes]);
    }
}
