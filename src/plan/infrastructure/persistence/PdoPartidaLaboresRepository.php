<?php

declare(strict_types=1);

namespace src\plan\infrastructure\persistence;

use InvalidArgumentException;
use PDO;
use src\ambito\domain\entity\Cuenta;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class PdoPartidaLaboresRepository implements PartidaLaboresRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function paraCentro(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT codigo, etiqueta, orden FROM centro_partidas_labores
             WHERE centro_id = :c AND activo = TRUE
             ORDER BY orden, codigo'
        );
        $st->execute([':c' => $centroId]);
        $rows = $st->fetchAll();
        if ($rows !== []) {
            return array_map($this->mapRow(...), $rows);
        }

        self::sembrarLegacy($this->pdo, $centroId);

        return CatalogoPlanesContables::partidasLaboresLegacy();
    }

    public function sembrarPorDefecto(int $centroId): void
    {
        self::insertarPartidas($this->pdo, $centroId, CatalogoPlanesContables::partidasLaboresPorDefecto());
    }

    public static function sembrarLegacy(PDO $pdo, int $centroId): void
    {
        self::insertarPartidas($pdo, $centroId, CatalogoPlanesContables::partidasLaboresLegacy());
    }

    /**
     * @param list<array{codigo:string,etiqueta:string,orden:int}> $partidas
     */
    private static function insertarPartidas(PDO $pdo, int $centroId, array $partidas): void
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM centro_partidas_labores WHERE centro_id = :c LIMIT 1'
        );
        $st->execute([':c' => $centroId]);
        if ($st->fetchColumn() !== false) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO centro_partidas_labores (centro_id, codigo, etiqueta, orden, activo)
             VALUES (:c, :codigo, :etiqueta, :orden, TRUE)'
        );
        foreach ($partidas as $p) {
            $ins->execute([
                ':c' => $centroId,
                ':codigo' => $p['codigo'],
                ':etiqueta' => $p['etiqueta'],
                ':orden' => $p['orden'],
            ]);
        }
    }

    public function guardar(int $centroId, array $partidas): void
    {
        $actuales = $this->codigosActivos($centroId);
        $nuevos = array_map(static fn (array $p): string => $p['codigo'], $partidas);
        foreach (array_diff($actuales, $nuevos) as $codigo) {
            if ($this->cuentaTieneMovimientos($centroId, $codigo)) {
                throw new InvalidArgumentException(
                    'No se puede quitar la partida ' . $codigo . ': ya tiene movimientos contables'
                );
            }
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM centro_partidas_labores WHERE centro_id = :c');
            $del->execute([':c' => $centroId]);

            $ins = $this->pdo->prepare(
                'INSERT INTO centro_partidas_labores (centro_id, codigo, etiqueta, orden, activo)
                 VALUES (:c, :codigo, :etiqueta, :orden, TRUE)'
            );
            foreach ($partidas as $p) {
                $ins->execute([
                    ':c' => $centroId,
                    ':codigo' => $p['codigo'],
                    ':etiqueta' => $p['etiqueta'],
                    ':orden' => $p['orden'],
                ]);
            }

            $this->sincronizarCuentas($centroId, $partidas, $actuales, $nuevos);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param list<array{codigo:string,etiqueta:string,orden:int}> $partidas
     * @param list<string> $actuales
     * @param list<string> $nuevos
     */
    private function sincronizarCuentas(int $centroId, array $partidas, array $actuales, array $nuevos): void
    {
        foreach ($partidas as $p) {
            $this->upsertCuentaLabores($centroId, $p['codigo'], $p['etiqueta'], $p['orden'], true);
        }
        foreach (array_diff($actuales, $nuevos) as $codigo) {
            $this->desactivarCuentaLabores($centroId, $codigo);
        }
    }

    private function upsertCuentaLabores(
        int $centroId,
        string $codigo,
        string $etiqueta,
        int $orden,
        bool $activo,
    ): void {
        $existente = $this->buscarCuenta($centroId, $codigo);
        if ($existente === null) {
            $cuenta = new Cuenta(
                null,
                $centroId,
                null,
                null,
                null,
                'P',
                $codigo,
                $etiqueta,
                $etiqueta,
                'gasto',
                'deudora',
                $codigo,
                true,
                $orden,
                $activo,
            );
            $this->insertarCuenta($cuenta);

            return;
        }

        $st = $this->pdo->prepare(
            'UPDATE cuentas SET nombre = :nombre, descripcion = :desc, orden = :orden, activo = :activo
             WHERE id = :id'
        );
        $st->execute([
            ':nombre' => $etiqueta,
            ':desc' => $etiqueta,
            ':orden' => $orden,
            ':activo' => (int) $activo,
            ':id' => $existente['id'],
        ]);
    }

    private function desactivarCuentaLabores(int $centroId, string $codigo): void
    {
        $st = $this->pdo->prepare(
            "UPDATE cuentas SET activo = FALSE
             WHERE centro_id = :c AND persona_id IS NULL AND libro = 'P' AND codigo = :codigo"
        );
        $st->execute([':c' => $centroId, ':codigo' => $codigo]);
    }

    /** @return array{id:int}|null */
    private function buscarCuenta(int $centroId, string $codigo): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM cuentas
             WHERE centro_id = :c AND persona_id IS NULL AND libro = 'P' AND codigo = :codigo
             LIMIT 1"
        );
        $st->execute([':c' => $centroId, ':codigo' => $codigo]);
        $row = $st->fetch();

        return is_array($row) ? ['id' => (int) $row['id']] : null;
    }

    private function insertarCuenta(Cuenta $cuenta): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO cuentas (centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
                nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden, activo)
             VALUES (:centro, NULL, NULL, NULL, :libro, :codigo,
                :nombre, :descripcion, :tipo, :naturaleza, :maestro, :imputable, :orden, :activo)'
        );
        $st->execute([
            ':centro' => $cuenta->centroId,
            ':libro' => $cuenta->libro,
            ':codigo' => $cuenta->codigo,
            ':nombre' => $cuenta->nombre,
            ':descripcion' => $cuenta->descripcion,
            ':tipo' => $cuenta->tipo,
            ':naturaleza' => $cuenta->naturaleza,
            ':maestro' => $cuenta->codigoMaestro,
            ':imputable' => (int) $cuenta->imputable,
            ':orden' => $cuenta->orden,
            ':activo' => (int) $cuenta->activo,
        ]);
    }

    private function cuentaTieneMovimientos(int $centroId, string $codigo): bool
    {
        $cuenta = $this->buscarCuenta($centroId, $codigo);
        if ($cuenta === null) {
            return false;
        }
        $st = $this->pdo->prepare('SELECT 1 FROM movimientos WHERE cuenta_id = :id LIMIT 1');
        $st->execute([':id' => $cuenta['id']]);

        return $st->fetchColumn() !== false;
    }

    /** @return list<string> */
    private function codigosActivos(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT codigo FROM centro_partidas_labores WHERE centro_id = :c ORDER BY orden, codigo'
        );
        $st->execute([':c' => $centroId]);

        return array_map(static fn (array $row): string => (string) $row['codigo'], $st->fetchAll());
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): array
    {
        return [
            'codigo' => (string) $row['codigo'],
            'etiqueta' => (string) $row['etiqueta'],
            'orden' => (int) $row['orden'],
        ];
    }
}
