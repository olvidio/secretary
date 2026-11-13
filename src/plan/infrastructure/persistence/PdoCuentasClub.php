<?php

declare(strict_types=1);

namespace src\plan\infrastructure\persistence;

use InvalidArgumentException;
use PDO;

/**
 * Modificación de las cuentas propias de una associació (libro G, sin persona).
 *
 * Los movimientos apuntan a la cuenta por su id, así que al cambiar el código lo
 * siguen sin tocarlos. Lo que guarda el código como texto (historial del extracto,
 * plantillas, entradas periódicas, filtros de listados) se actualiza aquí.
 */
final class PdoCuentasClub
{
    /** Códigos que usa el programa por su nombre: no se renombran. */
    private const PREFIJOS_FIJOS = ['BANCO.PEND.', 'OTRA.'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function esFija(string $codigo): bool
    {
        if ($codigo === 'APERTURA') {
            return true;
        }
        foreach (self::PREFIJOS_FIJOS as $prefijo) {
            if (str_starts_with($codigo, $prefijo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{codigo:string,nombre:string,naturaleza:?string,codigo_oficial:?string} $datos
     * @return array{pendiente:bool,aviso:string}
     */
    public function modificar(int $centroId, string $codigoActual, array $datos, bool $confirmado): array
    {
        $cuenta = $this->cuenta($centroId, $codigoActual);
        if ($cuenta === null) {
            throw new InvalidArgumentException(_('La cuenta no existe'));
        }
        $nuevo = $datos['codigo'];
        $nombre = $datos['nombre'];
        if ($nombre === '') {
            throw new InvalidArgumentException(_('El nombre es obligatorio'));
        }
        $fija = self::esFija($codigoActual);
        $tipo = (string) $cuenta['tipo'];
        if ($datos['naturaleza'] !== null && $tipo !== 'patrimonio' && !$fija) {
            $tipo = $datos['naturaleza'];
        }

        $cambios = [];
        if ($nuevo !== $codigoActual) {
            if ($fija) {
                throw new InvalidArgumentException(_('El código de esta cuenta lo usa el programa y no se puede cambiar'));
            }
            $cambios = $this->cambiosDeCodigo($centroId, $cuenta, $nuevo);
            if (!$confirmado) {
                return ['pendiente' => true, 'aviso' => $this->aviso($cambios)];
            }
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($cambios as $c) {
                $this->pdo->prepare('UPDATE cuentas SET codigo = :n, codigo_maestro = :n WHERE id = :id')
                    ->execute([':n' => $c['nuevo'], ':id' => $c['id']]);
                $this->renombrarReferencias($centroId, $c['antiguo'], $c['nuevo']);
            }
            if ($cambios !== []) {
                $this->renombrarEnListados($centroId, array_column($cambios, 'nuevo', 'antiguo'));
            }
            $this->pdo->prepare(
                'UPDATE cuentas SET nombre = :n, descripcion = :n, tipo = :t, naturaleza = :nat, codigo_oficial = :o
                 WHERE id = :id'
            )->execute([
                ':n' => $nombre,
                ':t' => $tipo,
                ':nat' => $tipo === 'gasto' ? 'deudora' : 'acreedora',
                // Los grupos no llevan código oficial.
                ':o' => $cuenta['imputable'] ? $datos['codigo_oficial'] : null,
                ':id' => $cuenta['id'],
            ]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return ['pendiente' => false, 'aviso' => ''];
    }

    /** @return array{id:int,codigo:string,tipo:string,imputable:bool}|null */
    private function cuenta(int $centroId, string $codigo): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT id, codigo, tipo, imputable FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND persona_id IS NULL AND codigo = :cod
               AND tipo IN ('ingreso', 'gasto', 'patrimonio')"
        );
        $st->execute([':c' => $centroId, ':cod' => $codigo]);
        $row = $st->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'codigo' => (string) $row['codigo'],
            'tipo' => (string) $row['tipo'],
            'imputable' => (bool) $row['imputable'],
        ];
    }

    /**
     * La cuenta y, si es un grupo, sus hijas (61 → 65 lleva 61.1 a 65.1).
     *
     * @param array{id:int,codigo:string,tipo:string,imputable:bool} $cuenta
     * @return list<array{id:int,antiguo:string,nuevo:string,movimientos:int}>
     */
    private function cambiosDeCodigo(int $centroId, array $cuenta, string $nuevo): array
    {
        $antiguo = $cuenta['codigo'];
        if (preg_match('/^[A-Za-z0-9._-]{1,16}$/', $nuevo) !== 1 || self::esFija($nuevo)) {
            throw new InvalidArgumentException(_('Código de cuenta no válido'));
        }
        if (str_starts_with($nuevo, $antiguo . '.')) {
            throw new InvalidArgumentException(_('El código nuevo no puede colgar del antiguo'));
        }
        $st = $this->pdo->prepare(
            "SELECT c.id, c.codigo, (SELECT COUNT(*) FROM movimientos m WHERE m.cuenta_id = c.id) AS movimientos
             FROM cuentas c
             WHERE c.centro_id = :c AND c.libro = 'G' AND c.persona_id IS NULL"
        );
        $st->execute([':c' => $centroId]);
        $cambios = [];
        $ocupados = [];
        foreach ($st->fetchAll() as $row) {
            $codigo = (string) $row['codigo'];
            $esLaCuenta = (int) $row['id'] === $cuenta['id'];
            $esHija = !$cuenta['imputable'] && str_starts_with($codigo, $antiguo . '.');
            if (!$esLaCuenta && !$esHija) {
                $ocupados[$codigo] = true;
                continue;
            }
            $cambios[] = [
                'id' => (int) $row['id'],
                'antiguo' => $codigo,
                'nuevo' => $esLaCuenta ? $nuevo : $nuevo . substr($codigo, strlen($antiguo)),
                'movimientos' => (int) $row['movimientos'],
            ];
        }
        foreach ($cambios as $c) {
            if (isset($ocupados[$c['nuevo']])) {
                throw new InvalidArgumentException(sprintf(_('Ya existe una cuenta con el código %s'), $c['nuevo']));
            }
            if (strlen($c['nuevo']) > 16) {
                throw new InvalidArgumentException(sprintf(_('El código %s es demasiado largo'), $c['nuevo']));
            }
        }
        usort($cambios, static fn (array $a, array $b): int => strnatcmp($a['antiguo'], $b['antiguo']));

        return $cambios;
    }

    /** @param list<array{id:int,antiguo:string,nuevo:string,movimientos:int}> $cambios */
    private function aviso(array $cambios): string
    {
        $pares = [];
        $movimientos = 0;
        foreach ($cambios as $c) {
            $pares[] = $c['antiguo'] . ' → ' . $c['nuevo'];
            $movimientos += $c['movimientos'];
        }

        return sprintf(
            _('Se va a cambiar el código: %s. Los %d movimientos de esas cuentas pasarán al código nuevo, igual que las plantillas, las entradas periódicas, los listados y el historial del banco que las usan. ¿Continuar?'),
            implode(', ', $pares),
            $movimientos,
        );
    }

    private function renombrarReferencias(int $centroId, string $antiguo, string $nuevo): void
    {
        $params = [':n' => $nuevo, ':a' => $antiguo, ':c' => $centroId];
        $this->pdo->prepare(
            'UPDATE banco_centro_import_filas SET concepto_asignado = :n
             WHERE centro_id = :c AND concepto_asignado = :a'
        )->execute($params);
        $this->pdo->prepare(
            'UPDATE entradas_periodicas SET concepto_codigo = :n WHERE centro_id = :c AND concepto_codigo = :a'
        )->execute($params);
        $this->pdo->prepare(
            "UPDATE plantilla_lineas_apunte l SET concepto_codigo = :n
             FROM plantillas_apunte p
             WHERE p.id = l.plantilla_id AND p.centro_id = :c AND l.concepto_codigo = :a
               AND l.cuenta = 'G'"
        )->execute($params);
    }

    /** @param array<string, string> $nuevoPorAntiguo */
    private function renombrarEnListados(int $centroId, array $nuevoPorAntiguo): void
    {
        $st = $this->pdo->prepare('SELECT id, categorias FROM listados WHERE centro_id = :c');
        $st->execute([':c' => $centroId]);
        $upd = $this->pdo->prepare('UPDATE listados SET categorias = :cat WHERE id = :id');
        foreach ($st->fetchAll() as $row) {
            $categorias = json_decode((string) $row['categorias'], true);
            if (!is_array($categorias)) {
                continue;
            }
            $nuevas = [];
            foreach ($categorias as $codigo) {
                $nuevas[] = $nuevoPorAntiguo[(string) $codigo] ?? (string) $codigo;
            }
            if ($nuevas !== $categorias) {
                $upd->execute([
                    ':cat' => json_encode(array_values(array_unique($nuevas)), JSON_UNESCAPED_UNICODE),
                    ':id' => $row['id'],
                ]);
            }
        }
    }
}
