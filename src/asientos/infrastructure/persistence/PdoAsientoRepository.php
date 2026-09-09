<?php

declare(strict_types=1);

namespace src\asientos\infrastructure\persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\asientos\domain\exceptions\AsientoDescuadrado;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoAsientoRepository implements AsientoRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function guardar(Asiento $asiento, bool $permitirEjercicioCerrado = false): Asiento
    {
        $asiento->assertCuadre();
        $this->validarCuentasImputables($asiento);
        $this->validarEjercicioAbierto($asiento->ejercicioId, $permitirEjercicioCerrado);

        $transaccionPropia = !$this->pdo->inTransaction();
        if ($transaccionPropia) {
            $this->pdo->beginTransaction();
        }
        try {
            $numero = $asiento->numero ?? $this->siguienteNumero($asiento->ejercicioId, $asiento->libro);
            $fecha = (new ConverterDate('date', $asiento->fecha))->toPg();
            $fechaOperacion = (new ConverterDate('date', $asiento->fechaOperacion()))->toPg();

            $st = $this->pdo->prepare(
                'INSERT INTO asientos (ejercicio_id, libro, numero, fecha, fecha_operacion, glosa, tipo, origen, persona_id, asiento_par_id, remesa_id)
                 VALUES (:ej, :lib, :num, :fecha, :fecha_op, :glosa, :tipo, :origen, :persona, :par, :remesa)
                 RETURNING id'
            );
            $st->execute([
                ':ej' => $asiento->ejercicioId,
                ':lib' => $asiento->libro,
                ':num' => $numero,
                ':fecha' => $fecha,
                ':fecha_op' => $fechaOperacion,
                ':glosa' => $asiento->glosa,
                ':tipo' => $asiento->tipo,
                ':origen' => $asiento->origen,
                ':persona' => $asiento->personaId,
                ':par' => $asiento->asientoParId,
                ':remesa' => $asiento->remesaId,
            ]);
            $asientoId = (int) $st->fetchColumn();

            $stMov = $this->pdo->prepare(
                'INSERT INTO movimientos (asiento_id, orden, cuenta_id, persona_id, debe, haber)
                 VALUES (:asiento, :orden, :cuenta, :persona, :debe, :haber) RETURNING id'
            );
            $movimientosGuardados = [];
            foreach ($asiento->movimientos as $mov) {
                $stMov->execute([
                    ':asiento' => $asientoId,
                    ':orden' => $mov->orden,
                    ':cuenta' => $mov->cuentaId,
                    ':persona' => $mov->personaId,
                    ':debe' => $mov->debeCents,
                    ':haber' => $mov->haberCents,
                ]);
                $movId = (int) $stMov->fetchColumn();
                $movimientosGuardados[] = new Movimiento(
                    $movId,
                    $mov->orden,
                    $mov->cuentaId,
                    $mov->personaId,
                    $mov->debeCents,
                    $mov->haberCents,
                );
            }

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return new Asiento(
                $asientoId,
                $asiento->ejercicioId,
                $asiento->libro,
                $numero,
                $asiento->fecha,
                $asiento->glosa,
                $asiento->tipo,
                $asiento->origen,
                $asiento->personaId,
                $movimientosGuardados,
                $asiento->conceptoCodigo,
                $asiento->asientoParId,
                $asiento->fechaOperacion(),
                $asiento->remesaId,
            );
        } catch (AsientoDescuadrado $e) {
            if ($transaccionPropia) {
                $this->pdo->rollBack();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($transaccionPropia) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar(Asiento $asiento): Asiento
    {
        if ($asiento->id === null) {
            throw new InvalidArgumentException('actualizar requiere un asiento persistido');
        }
        $asiento->assertCuadre();
        $this->validarCuentasImputables($asiento);
        $this->validarEjercicioAbierto($asiento->ejercicioId);

        $transaccionPropia = !$this->pdo->inTransaction();
        if ($transaccionPropia) {
            $this->pdo->beginTransaction();
        }
        try {
            $fecha = (new ConverterDate('date', $asiento->fecha))->toPg();
            $fechaOperacion = (new ConverterDate('date', $asiento->fechaOperacion()))->toPg();
            $st = $this->pdo->prepare(
                'UPDATE asientos SET fecha = :fecha, fecha_operacion = :fecha_op, glosa = :glosa,
                    tipo = :tipo, origen = :origen, persona_id = :persona, updated_at = now()
                 WHERE id = :id'
            );
            $st->execute([
                ':fecha' => $fecha,
                ':fecha_op' => $fechaOperacion,
                ':glosa' => $asiento->glosa,
                ':tipo' => $asiento->tipo,
                ':origen' => $asiento->origen,
                ':persona' => $asiento->personaId,
                ':id' => $asiento->id,
            ]);

            $del = $this->pdo->prepare('DELETE FROM movimientos WHERE asiento_id = :id');
            $del->execute([':id' => $asiento->id]);

            $stMov = $this->pdo->prepare(
                'INSERT INTO movimientos (asiento_id, orden, cuenta_id, persona_id, debe, haber)
                 VALUES (:asiento, :orden, :cuenta, :persona, :debe, :haber) RETURNING id'
            );
            foreach ($asiento->movimientos as $mov) {
                $stMov->execute([
                    ':asiento' => $asiento->id,
                    ':orden' => $mov->orden,
                    ':cuenta' => $mov->cuentaId,
                    ':persona' => $mov->personaId,
                    ':debe' => $mov->debeCents,
                    ':haber' => $mov->haberCents,
                ]);
            }

            $releido = $this->porId($asiento->id);
            if ($releido === null) {
                throw new RuntimeException('No se pudo releer el asiento actualizado');
            }

            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return $releido;
        } catch (\Throwable $e) {
            if ($transaccionPropia) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function anular(int $id): void
    {
        $st = $this->pdo->prepare(
            "UPDATE asientos SET anulado_at = now(), updated_at = now()
             WHERE id = :id AND origen = 'import' AND anulado_at IS NULL"
        );
        $st->execute([':id' => $id]);
    }

    public function borrarPorOrigen(int $ejercicioId, string $origen): int
    {
        $st = $this->pdo->prepare(
            'DELETE FROM asientos WHERE ejercicio_id = :ej AND origen = :origen'
        );
        $st->execute([':ej' => $ejercicioId, ':origen' => $origen]);

        return $st->rowCount();
    }

    public function enlazar(int $idA, int $idB): void
    {
        $transaccionPropia = !$this->pdo->inTransaction();
        if ($transaccionPropia) {
            $this->pdo->beginTransaction();
        }
        try {
            $st = $this->pdo->prepare('UPDATE asientos SET asiento_par_id = :par WHERE id = :id');
            $st->execute([':par' => $idB, ':id' => $idA]);
            $st->execute([':par' => $idA, ':id' => $idB]);
            if ($transaccionPropia) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transaccionPropia) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function guardarEnlazados(Asiento $primero, Asiento $segundo, bool $primeroPermiteCerrado = false): array
    {
        $transaccionPropia = !$this->pdo->inTransaction();
        if ($transaccionPropia) {
            $this->pdo->beginTransaction();
        }
        try {
            $guardadoPrimero = $this->guardar($primero, $primeroPermiteCerrado);
            $guardadoSegundo = $this->guardar($segundo, false);
            if ($guardadoPrimero->id === null || $guardadoSegundo->id === null) {
                throw new RuntimeException('No se pudieron guardar los asientos enlazados');
            }
            $this->enlazar($guardadoPrimero->id, $guardadoSegundo->id);
            if ($transaccionPropia) {
                $this->pdo->commit();
            }
            $releidoPrimero = $this->porId($guardadoPrimero->id);
            $releidoSegundo = $this->porId($guardadoSegundo->id);
            if ($releidoPrimero === null || $releidoSegundo === null) {
                throw new RuntimeException('No se pudieron releer los asientos enlazados');
            }

            return [$releidoPrimero, $releidoSegundo];
        } catch (\Throwable $e) {
            if ($transaccionPropia && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function traspasarPeriodificacion(
        int $ejercicioOrigenId,
        int $ejercicioDestinoId,
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
    ): array {
        $this->validarEjercicioAbierto($ejercicioDestinoId);
        $transaccionPropia = !$this->pdo->inTransaction();
        if ($transaccionPropia) {
            $this->pdo->beginTransaction();
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT id FROM asientos
                 WHERE ejercicio_id = :origen AND tipo = 'periodificacion' AND anulado_at IS NULL
                   AND fecha >= :desde AND fecha <= :hasta
                 ORDER BY fecha, numero, id"
            );
            $st->execute([
                ':origen' => $ejercicioOrigenId,
                ':desde' => (new ConverterDate('date', $desde))->toPg(),
                ':hasta' => (new ConverterDate('date', $hasta))->toPg(),
            ]);
            $movidos = [];
            $upd = $this->pdo->prepare(
                'UPDATE asientos SET ejercicio_id = :dest, numero = :num WHERE id = :id'
            );
            foreach ($st->fetchAll() as $row) {
                $asiento = $this->porId((int) $row['id']);
                if ($asiento === null) {
                    continue;
                }
                $numero = $this->siguienteNumero($ejercicioDestinoId, $asiento->libro);
                $upd->execute([
                    ':dest' => $ejercicioDestinoId,
                    ':num' => $numero,
                    ':id' => (int) $asiento->id,
                ]);
                $releido = $this->porId((int) $asiento->id);
                if ($releido !== null) {
                    $movidos[] = $releido;
                }
            }
            if ($transaccionPropia) {
                $this->pdo->commit();
            }

            return $movidos;
        } catch (\Throwable $e) {
            if ($transaccionPropia) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function porId(int $id): ?Asiento
    {
        $st = $this->pdo->prepare('SELECT * FROM asientos WHERE id = :id AND anulado_at IS NULL');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrateAsiento($row) : null;
    }

    public function borrarPorRemesaId(int $remesaId): int
    {
        $st = $this->pdo->prepare('DELETE FROM asientos WHERE remesa_id = :id');
        $st->execute([':id' => $remesaId]);

        return $st->rowCount();
    }

    public function borrar(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare('SELECT asiento_par_id FROM asientos WHERE id = :id');
            $st->execute([':id' => $id]);
            $par = $st->fetchColumn();
            $parId = ($par === false || $par === null) ? null : (int) $par;

            $clear = $this->pdo->prepare(
                'UPDATE asientos SET asiento_par_id = NULL WHERE id = :a OR id = :b OR asiento_par_id = :c OR asiento_par_id = :d'
            );
            $clear->execute([
                ':a' => $id,
                ':b' => $parId ?? $id,
                ':c' => $id,
                ':d' => $parId ?? $id,
            ]);

            $del = $this->pdo->prepare('DELETE FROM asientos WHERE id = :id');
            $del->execute([':id' => $id]);
            if ($parId !== null && $parId !== $id) {
                $del->execute([':id' => $parId]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listar(int $ejercicioId, array $filtros = []): array
    {
        $sql = 'SELECT a.* FROM asientos a WHERE a.ejercicio_id = :ej AND a.anulado_at IS NULL';
        $params = [':ej' => $ejercicioId];

        $libro = $filtros['cuenta'] ?? $filtros['libro'] ?? null;
        if (!empty($libro)) {
            $sql .= ' AND a.libro = :lib';
            $params[':lib'] = strtoupper((string) $libro);
        } else {
            $sql .= " AND a.libro IN ('P', 'G')";
        }
        if (!empty($filtros['desde'])) {
            $sql .= ' AND a.fecha >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND a.fecha <= :hasta';
            $params[':hasta'] = $filtros['hasta'];
        }
        if (!empty($filtros['tipo'])) {
            $sql .= ' AND a.tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }
        if (isset($filtros['es_cierre'])) {
            if ($filtros['es_cierre']) {
                $sql .= " AND a.tipo = 'cierre'";
            } else {
                $sql .= " AND a.tipo <> 'cierre'";
            }
        }
        if (!empty($filtros['iniciales'])) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM personas p
                WHERE p.id = a.persona_id AND p.iniciales = :iniciales
            )';
            $params[':iniciales'] = strtolower((string) $filtros['iniciales']);
        }
        if (isset($filtros['persona_id']) && (int) $filtros['persona_id'] > 0) {
            $sql .= ' AND a.persona_id = :pid';
            $params[':pid'] = (int) $filtros['persona_id'];
        }

        $sql .= ' ORDER BY a.fecha, a.numero, a.id';

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $asientos = [];
        foreach ($st->fetchAll() as $row) {
            $asiento = $this->hydrateAsiento($row);
            if ($this->pasaFiltrosProyeccion($asiento, $filtros)) {
                $asientos[] = $asiento;
            }
        }

        return $asientos;
    }

    /**
     * Filtros concepto/origen requieren la proyección; se aplican en PHP tras cargar.
     *
     * @param array<string, mixed> $filtros
     */
    private function pasaFiltrosProyeccion(Asiento $asiento, array $filtros): bool
    {
        if (!empty($filtros['concepto'])) {
            $codigo = $asiento->conceptoCodigo;
            if ($codigo === null) {
                foreach ($asiento->movimientos as $mov) {
                    $st = $this->pdo->prepare('SELECT codigo FROM cuentas WHERE id = :id');
                    $st->execute([':id' => $mov->cuentaId]);
                    $c = $st->fetchColumn();
                    if (is_string($c) && $c === $filtros['concepto']) {
                        return true;
                    }
                }

                return false;
            }
            if ($codigo !== $filtros['concepto']) {
                return false;
            }
        }

        return true;
    }

    public function listarPorEjercicio(int $ejercicioId, ?string $libro = null): array
    {
        $filtros = [];
        if ($libro !== null) {
            $filtros['libro'] = $libro;
        }

        return $this->listar($ejercicioId, $filtros);
    }

    public function borrarPorEjercicio(int $ejercicioId): void
    {
        $st = $this->pdo->prepare('DELETE FROM asientos WHERE ejercicio_id = :ej');
        $st->execute([':ej' => $ejercicioId]);
    }

    public function borrarCierresEntre(int $ejercicioId, DateTimeImmutable $desde, DateTimeImmutable $hasta): void
    {
        $st = $this->pdo->prepare(
            "DELETE FROM asientos WHERE ejercicio_id = :ej AND tipo = 'cierre'
             AND fecha >= :d AND fecha <= :h"
        );
        $st->execute([
            ':ej' => $ejercicioId,
            ':d' => (new ConverterDate('date', $desde))->toPg(),
            ':h' => (new ConverterDate('date', $hasta))->toPg(),
        ]);
    }

    public function borrarAperturas(int $ejercicioId): void
    {
        $st = $this->pdo->prepare("DELETE FROM asientos WHERE ejercicio_id = :ej AND tipo = 'apertura'");
        $st->execute([':ej' => $ejercicioId]);
    }

    public function reemplazarAperturas(int $ejercicioId, array $asientos): array
    {
        $this->pdo->beginTransaction();
        try {
            $this->borrarAperturas($ejercicioId);
            $guardados = [];
            foreach ($asientos as $asiento) {
                $guardados[] = $this->guardar($asiento);
            }
            $this->pdo->commit();

            return $guardados;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function contarApertura(int $ejercicioId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM asientos WHERE ejercicio_id = :ej AND anulado_at IS NULL AND tipo = 'apertura'"
        );
        $st->execute([':ej' => $ejercicioId]);

        return (int) $st->fetchColumn();
    }

    public function contarNoApertura(int $ejercicioId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM asientos WHERE ejercicio_id = :ej AND anulado_at IS NULL AND tipo <> 'apertura'"
        );
        $st->execute([':ej' => $ejercicioId]);

        return (int) $st->fetchColumn();
    }

    public function hayDescuadrados(int $ejercicioId): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM asientos a
             INNER JOIN movimientos m ON m.asiento_id = a.id
             WHERE a.ejercicio_id = :ej AND a.anulado_at IS NULL
             GROUP BY a.id
             HAVING SUM(m.debe) <> SUM(m.haber)
             LIMIT 1'
        );
        $st->execute([':ej' => $ejercicioId]);

        return $st->fetch() !== false;
    }

    private function validarEjercicioAbierto(int $ejercicioId, bool $permitirCerrado = false): void
    {
        $st = $this->pdo->prepare('SELECT estado FROM ejercicios WHERE id = :id');
        $st->execute([':id' => $ejercicioId]);
        $estado = $st->fetchColumn();
        if ($estado === false) {
            throw new InvalidArgumentException('Ejercicio no encontrado: id ' . $ejercicioId);
        }
        if ((string) $estado === 'cerrado' && !$permitirCerrado) {
            throw new InvalidArgumentException(
                'No se pueden registrar asientos en un ejercicio cerrado; reábralo o use el ejercicio abierto'
            );
        }
    }

    public function saldosPorCuenta(
        int $centroId,
        int $ejercicioId,
        ?string $desde = null,
        ?string $hasta = null,
        ?string $libro = null,
    ): array {
        $sql = 'SELECT c.id, c.libro, c.codigo, c.codigo_maestro, c.tipo, c.persona_id, c.cuenta_fisica_id,
                       COALESCE(SUM(m.debe - m.haber), 0) AS saldo_cents
                FROM cuentas c
                LEFT JOIN asientos a ON a.ejercicio_id = :ej AND a.anulado_at IS NULL
                LEFT JOIN movimientos m ON m.asiento_id = a.id AND m.cuenta_id = c.id';
        $params = [':ej' => $ejercicioId, ':centro' => $centroId];

        if ($desde !== null) {
            $sql .= ' AND a.fecha >= :desde';
            $params[':desde'] = $desde;
        }
        if ($hasta !== null) {
            $sql .= ' AND a.fecha <= :hasta';
            $params[':hasta'] = $hasta;
        }

        $sql .= ' WHERE c.centro_id = :centro';
        if ($libro !== null) {
            $sql .= ' AND c.libro = :libro';
            $params[':libro'] = $libro;
        }
        $sql .= ' GROUP BY c.id ORDER BY c.libro, c.codigo';

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'libro' => (string) $row['libro'],
                'codigo' => (string) $row['codigo'],
                'codigo_maestro' => (string) $row['codigo_maestro'],
                'tipo' => (string) $row['tipo'],
                'persona_id' => $row['persona_id'] !== null ? (int) $row['persona_id'] : null,
                'cuenta_fisica_id' => $row['cuenta_fisica_id'] !== null ? (int) $row['cuenta_fisica_id'] : null,
                'saldo_cents' => (int) $row['saldo_cents'],
            ];
        }

        return $out;
    }

    public function realizadoPorConcepto(
        int $centroId,
        int $ejercicioId,
        string $libro,
        string $desde,
        string $hasta,
        array $excluirTipos = [],
    ): array {
        $sql = "SELECT c.codigo,
                       CASE
                         WHEN c.tipo IN ('ingreso', 'patrimonio') THEN COALESCE(SUM(m.haber - m.debe), 0)
                         WHEN c.tipo = 'gasto' THEN COALESCE(SUM(m.debe - m.haber), 0)
                         ELSE 0
                       END AS realizado_cents
                FROM cuentas c
                INNER JOIN movimientos m ON m.cuenta_id = c.id
                INNER JOIN asientos a ON a.id = m.asiento_id
                  AND a.ejercicio_id = :ej
                  AND a.libro = :lib
                  AND a.anulado_at IS NULL
                  AND a.fecha >= :desde
                  AND a.fecha <= :hasta";
        if ($excluirTipos !== []) {
            $placeholders = [];
            foreach ($excluirTipos as $i => $tipo) {
                $key = ':ex' . $i;
                $placeholders[] = $key;
            }
            $sql .= ' AND a.tipo NOT IN (' . implode(', ', $placeholders) . ')';
        }
        $sql .= " WHERE c.centro_id = :centro
                  AND c.libro = :lib
                  AND c.tipo IN ('ingreso', 'gasto', 'patrimonio')
                GROUP BY c.codigo, c.tipo";

        $params = [
            ':centro' => $centroId,
            ':ej' => $ejercicioId,
            ':lib' => $libro,
            ':desde' => $desde,
            ':hasta' => $hasta,
        ];
        foreach ($excluirTipos as $i => $tipo) {
            $params[':ex' . $i] = $tipo;
        }

        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(string) $row['codigo']] = (int) $row['realizado_cents'];
        }

        return $out;
    }

    public function movimientosE37PorPersona(
        int $centroId,
        int $ejercicioId,
        string $desde,
        string $hasta,
    ): array {
        $sql = "SELECT p.iniciales, c.codigo_maestro,
                       CASE
                         WHEN c.tipo IN ('ingreso', 'patrimonio') THEN COALESCE(SUM(m.haber - m.debe), 0)
                         WHEN c.tipo = 'gasto' THEN COALESCE(SUM(m.debe - m.haber), 0)
                         ELSE 0
                       END AS importe_cents
                FROM movimientos m
                INNER JOIN asientos a ON a.id = m.asiento_id
                  AND a.ejercicio_id = :ej
                  AND a.libro = 'P'
                  AND a.anulado_at IS NULL
                  AND a.fecha >= :desde
                  AND a.fecha <= :hasta
                INNER JOIN cuentas c ON c.id = m.cuenta_id
                  AND c.centro_id = :centro
                  AND c.tipo IN ('ingreso', 'gasto')
                INNER JOIN personas p ON p.id = COALESCE(m.persona_id, a.persona_id)
                GROUP BY p.iniciales, c.codigo_maestro, c.tipo";

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':centro' => $centroId,
            ':ej' => $ejercicioId,
            ':desde' => $desde,
            ':hasta' => $hasta,
        ]);

        $out = [];
        foreach ($st->fetchAll() as $row) {
            $ini = (string) $row['iniciales'];
            $out[$ini][(string) $row['codigo_maestro']] = (int) $row['importe_cents'];
        }

        return $out;
    }

    private function siguienteNumero(int $ejercicioId, string $libro): int
    {
        $st = $this->pdo->prepare(
            'SELECT COALESCE(MAX(numero), 0) + 1 FROM asientos WHERE ejercicio_id = :ej AND libro = :lib'
        );
        $st->execute([':ej' => $ejercicioId, ':lib' => $libro]);

        return (int) $st->fetchColumn();
    }

    private function validarCuentasImputables(Asiento $asiento): void
    {
        foreach ($asiento->movimientos as $mov) {
            $st = $this->pdo->prepare('SELECT imputable, codigo, libro FROM cuentas WHERE id = :id');
            $st->execute([':id' => $mov->cuentaId]);
            $row = $st->fetch();
            if (!is_array($row)) {
                throw new InvalidArgumentException('Cuenta no encontrada: id ' . $mov->cuentaId);
            }
            if (!(bool) $row['imputable']) {
                throw new InvalidArgumentException(
                    sprintf('La cuenta %s/%s no es imputable', $row['libro'], $row['codigo'])
                );
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrateAsiento(array $row): Asiento
    {
        $asientoId = (int) $row['id'];
        $st = $this->pdo->prepare(
            'SELECT * FROM movimientos WHERE asiento_id = :id ORDER BY orden'
        );
        $st->execute([':id' => $asientoId]);
        $movimientos = [];
        foreach ($st->fetchAll() as $movRow) {
            $movimientos[] = new Movimiento(
                (int) $movRow['id'],
                (int) $movRow['orden'],
                (int) $movRow['cuenta_id'],
                isset($movRow['persona_id']) ? (int) $movRow['persona_id'] : null,
                (int) $movRow['debe'],
                (int) $movRow['haber'],
            );
        }

        $fecha = (new ConverterDate('date', $row['fecha']))->fromPg();
        if ($fecha === null) {
            throw new RuntimeException('Fecha de asiento inválida');
        }
        $fechaOperacion = $fecha;
        if (isset($row['fecha_operacion'])) {
            $fechaOperacion = (new ConverterDate('date', $row['fecha_operacion']))->fromPg() ?? $fecha;
        }

        return new Asiento(
            $asientoId,
            (int) $row['ejercicio_id'],
            (string) $row['libro'],
            (int) $row['numero'],
            $fecha,
            isset($row['glosa']) ? (string) $row['glosa'] : null,
            (string) $row['tipo'],
            (string) $row['origen'],
            isset($row['persona_id']) ? (int) $row['persona_id'] : null,
            $movimientos,
            null,
            isset($row['asiento_par_id']) ? (int) $row['asiento_par_id'] : null,
            $fechaOperacion,
            isset($row['remesa_id']) ? (int) $row['remesa_id'] : null,
        );
    }
}
