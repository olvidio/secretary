<?php

declare(strict_types=1);

namespace src\personal\infrastructure\persistence;

use PDO;
use src\personal\application\AsegurarPlanPersonal;
use src\personal\domain\contracts\BancoImportRepository;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoBancoImportRepository implements BancoImportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function existe(int $personaId, string $banco, string $huella): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM banco_import_filas WHERE persona_id = :p AND banco = :b AND huella = :h'
        );
        $st->execute([':p' => $personaId, ':b' => $banco, ':h' => $huella]);

        return $st->fetchColumn() !== false;
    }

    public function porAsiento(int $asientoId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT banco, huella, fecha, importe, concepto
             FROM banco_import_filas WHERE asiento_id = :a LIMIT 1'
        );
        $st->execute([':a' => $asientoId]);
        $row = $st->fetch();
        if (!is_array($row)) {
            return null;
        }
        $fecha = (new ConverterDate('date', $row['fecha']))->fromPg();

        return [
            'banco' => (string) $row['banco'],
            'huella' => (string) $row['huella'],
            'fecha' => $fecha !== null ? $fecha->format('Y-m-d') : '',
            'importe' => (string) $row['importe'],
            'concepto' => (string) $row['concepto'],
        ];
    }

    public function guardar(
        int $personaId,
        string $banco,
        string $huella,
        int $asientoId,
        string $fecha,
        string $importe,
        string $concepto,
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO banco_import_filas (persona_id, banco, huella, asiento_id, fecha, importe, concepto)
             VALUES (:p, :b, :h, :a, :f, :i, :c)'
        );
        $st->execute([
            ':p' => $personaId,
            ':b' => $banco,
            ':h' => $huella,
            ':a' => $asientoId,
            ':f' => (new ConverterDate('date', $fecha))->toPg(),
            ':i' => $importe,
            ':c' => $concepto,
        ]);
    }

    public function deCuentas(int $personaId, array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }
        $ph = [];
        $params = [':p' => $personaId];
        foreach (array_values($codigos) as $i => $codigo) {
            $k = ':c' . $i;
            $ph[] = $k;
            $params[$k] = $codigo;
        }
        $st = $this->pdo->prepare(
            'SELECT f.asiento_id, f.fecha, f.importe, f.concepto, f.banco, a.glosa AS nota,
                    c.id AS categoria_id, c.nombre AS categoria
             FROM banco_import_filas f
             JOIN asientos a ON a.id = f.asiento_id AND a.anulado_at IS NULL
             JOIN movimientos m ON m.asiento_id = a.id
             JOIN cuentas c ON c.id = m.cuenta_id
             WHERE f.persona_id = :p
               AND c.codigo IN (' . implode(', ', $ph) . ')
             ORDER BY f.fecha DESC, f.id DESC'
        );
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $importe = (string) $row['importe'];
            $fecha = (new ConverterDate('date', $row['fecha']))->fromPg();
            $out[] = [
                'asiento_id' => (int) $row['asiento_id'],
                'fecha' => $fecha !== null ? $fecha->format('Y-m-d') : '',
                'importe' => $importe,
                'concepto' => (string) $row['concepto'],
                'nota' => isset($row['nota']) && is_string($row['nota']) ? (string) $row['nota'] : '',
                'banco' => (string) $row['banco'],
                'categoria_id' => isset($row['categoria_id']) ? (int) $row['categoria_id'] : null,
                'categoria' => is_string($row['categoria'] ?? null) ? (string) $row['categoria'] : null,
                'sentido' => str_starts_with($importe, '-') ? 'gasto' : 'ingreso',
            ];
        }

        return $out;
    }

    public function historialCategorizado(int $personaId): array
    {
        $st = $this->pdo->prepare(
            'SELECT f.concepto, c.id AS cuenta_id, c.tipo
             FROM banco_import_filas f
             JOIN asientos a ON a.id = f.asiento_id AND a.anulado_at IS NULL
             JOIN movimientos m ON m.asiento_id = a.id
             JOIN cuentas c ON c.id = m.cuenta_id
             WHERE f.persona_id = :p
               AND c.tipo IN (\'ingreso\', \'gasto\')
               AND c.codigo NOT IN (:g, :i)
             ORDER BY f.fecha DESC, f.id DESC'
        );
        $st->execute([
            ':p' => $personaId,
            ':g' => AsegurarPlanPersonal::CODIGO_PENDIENTE_GASTO,
            ':i' => AsegurarPlanPersonal::CODIGO_PENDIENTE_INGRESO,
        ]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'concepto' => (string) $row['concepto'],
                'cuenta_id' => (int) $row['cuenta_id'],
                'tipo' => (string) $row['tipo'],
            ];
        }

        return $out;
    }
}
