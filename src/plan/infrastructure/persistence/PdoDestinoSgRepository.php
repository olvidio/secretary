<?php

declare(strict_types=1);

namespace src\plan\infrastructure\persistence;

use InvalidArgumentException;
use PDO;
use src\plan\domain\contracts\DestinoSgRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class PdoDestinoSgRepository implements DestinoSgRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function nombrados(int $centroId): ?array
    {
        if (!$this->esH16s($centroId)) {
            return null;
        }
        $map = [];
        foreach ($this->paraCentro($centroId) as $p) {
            $map[$p['codigo']] = $p['etiqueta'];
        }

        return $map;
    }

    public function paraCentro(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT codigo, etiqueta, orden FROM centro_destinos_sg
             WHERE centro_id = :c ORDER BY orden, codigo'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'codigo' => (string) $row['codigo'],
                'etiqueta' => (string) $row['etiqueta'],
                'orden' => (int) $row['orden'],
            ];
        }

        return $out;
    }

    public function guardar(int $centroId, array $destinos): void
    {
        if (!$this->esH16s($centroId)) {
            throw new InvalidArgumentException(_('Este centro no usa destinos propios'));
        }
        $actuales = array_column($this->paraCentro($centroId), 'codigo');
        $nuevos = array_map(static fn (array $p): string => $p['codigo'], $destinos);
        foreach (array_diff($actuales, $nuevos) as $codigo) {
            if ($this->cuentaTieneMovimientos($centroId, $codigo)) {
                throw new InvalidArgumentException(
                    sprintf(_('No se puede quitar el destino %s: ya tiene apuntes'), $codigo)
                );
            }
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM centro_destinos_sg WHERE centro_id = :c');
            $del->execute([':c' => $centroId]);
            $ins = $this->pdo->prepare(
                'INSERT INTO centro_destinos_sg (centro_id, codigo, etiqueta, orden)
                 VALUES (:c, :codigo, :etiqueta, :orden)'
            );
            foreach ($destinos as $p) {
                $ins->execute([
                    ':c' => $centroId,
                    ':codigo' => $p['codigo'],
                    ':etiqueta' => $p['etiqueta'],
                    ':orden' => $p['orden'],
                ]);
                $this->upsertCuenta($centroId, $p['codigo'], $p['etiqueta'], $p['orden']);
            }
            foreach (array_diff($actuales, $nuevos) as $codigo) {
                $this->upsertCuenta($centroId, $codigo, $codigo, (int) $codigo + 400);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function esH16s(int $centroId): bool
    {
        $st = $this->pdo->prepare(
            "SELECT COALESCE(p.codigo, '') FROM centros c
             LEFT JOIN planes_contables p ON p.id = c.plan_contable_id
             WHERE c.id = :c"
        );
        $st->execute([':c' => $centroId]);
        $codigo = $st->fetchColumn();

        return is_string($codigo) && CatalogoPlanesContables::esCentroSg($codigo);
    }

    private function upsertCuenta(int $centroId, string $codigo, string $etiqueta, int $orden): void
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM cuentas
             WHERE centro_id = :c AND persona_id IS NULL AND libro = 'G' AND codigo = :codigo
             LIMIT 1"
        );
        $st->execute([':c' => $centroId, ':codigo' => $codigo]);
        $id = $st->fetchColumn();
        if ($id === false) {
            $ins = $this->pdo->prepare(
                'INSERT INTO cuentas (centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
                    nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden, activo)
                 VALUES (:centro, NULL, NULL, NULL, \'G\', :codigo,
                    :nombre, :desc, \'gasto\', \'deudora\', :codigo, 1, :orden, 1)'
            );
            $ins->execute([
                ':centro' => $centroId,
                ':codigo' => $codigo,
                ':nombre' => $etiqueta,
                ':desc' => $etiqueta,
                ':orden' => $orden,
            ]);

            return;
        }
        $upd = $this->pdo->prepare(
            'UPDATE cuentas SET nombre = :nombre, descripcion = :desc, orden = :orden, activo = TRUE
             WHERE id = :id'
        );
        $upd->execute([
            ':nombre' => $etiqueta,
            ':desc' => $etiqueta,
            ':orden' => $orden,
            ':id' => $id,
        ]);
    }

    private function cuentaTieneMovimientos(int $centroId, string $codigo): bool
    {
        $st = $this->pdo->prepare(
            "SELECT 1 FROM movimientos m
             JOIN cuentas c ON c.id = m.cuenta_id
             WHERE c.centro_id = :c AND c.persona_id IS NULL AND c.libro = 'G' AND c.codigo = :codigo
             LIMIT 1"
        );
        $st->execute([':c' => $centroId, ':codigo' => $codigo]);

        return $st->fetchColumn() !== false;
    }
}
