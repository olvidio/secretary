<?php

declare(strict_types=1);

namespace src\personal\infrastructure\persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\personal\application\AsegurarPlanPersonal;
use src\personal\domain\contracts\CopiaPersonalRepository;
use src\personal\domain\contracts\PersonalCierreRepository;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoCopiaPersonalRepository implements CopiaPersonalRepository
{
    private const VERSION = 1;

    public function __construct(
        private readonly PDO $pdo,
        private readonly CuentaRepository $cuentas,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsientoRepository $asientos,
        private readonly PersonalCierreRepository $cierres,
        private readonly AsegurarPlanPersonal $asegurar,
    ) {
    }

    public function exportar(int $centroId, int $personaId): array
    {
        $this->asegurar->ejecutar($centroId, $personaId);
        $cuentas = $this->mapaCuentas($centroId, $personaId);
        $iniciales = $this->inicialesDe($personaId);

        $st = $this->pdo->prepare(
            'SELECT a.* FROM asientos a
             WHERE a.libro = \'X\' AND a.persona_id = :p AND a.anulado_at IS NULL
             ORDER BY a.fecha, a.numero, a.id'
        );
        $st->execute([':p' => $personaId]);
        $asientos = [];
        foreach ($st->fetchAll() as $row) {
            $id = (int) $row['id'];
            $fecha = (new ConverterDate('date', $row['fecha']))->fromPg();
            $fechaOp = (new ConverterDate('date', $row['fecha_operacion'] ?? $row['fecha']))->fromPg();
            if ($fecha === null || $fechaOp === null) {
                continue;
            }
            $lineas = [];
            $stM = $this->pdo->prepare(
                'SELECT m.*, c.codigo AS cuenta_codigo
                 FROM movimientos m
                 JOIN cuentas c ON c.id = m.cuenta_id
                 WHERE m.asiento_id = :a ORDER BY m.orden'
            );
            $stM->execute([':a' => $id]);
            foreach ($stM->fetchAll() as $mov) {
                $lineas[] = [
                    'cuenta_codigo' => (string) $mov['cuenta_codigo'],
                    'debe_cents' => (int) $mov['debe'],
                    'haber_cents' => (int) $mov['haber'],
                ];
            }
            $asientos[] = [
                'ref' => $id,
                'par_ref' => isset($row['asiento_par_id']) && $row['asiento_par_id'] !== null
                    ? (int) $row['asiento_par_id'] : null,
                'fecha' => $fecha->format('Y-m-d'),
                'fecha_operacion' => $fechaOp->format('Y-m-d'),
                'glosa' => isset($row['glosa']) ? (string) $row['glosa'] : null,
                'tipo' => (string) $row['tipo'],
                'origen' => (string) $row['origen'],
                'gasto_generales' => !empty($row['gasto_generales']),
                'concepto_generales' => isset($row['concepto_generales']) && $row['concepto_generales'] !== ''
                    ? (string) $row['concepto_generales'] : null,
                'lineas' => $lineas,
            ];
        }

        $subcuentas = [];
        foreach ($cuentas as $c) {
            if (!in_array($c->tipo, ['ingreso', 'gasto'], true) || $c->padreId === null) {
                continue;
            }
            if (AsegurarPlanPersonal::esPendiente($c->codigo) || AsegurarPlanPersonal::esOtra($c->codigo)) {
                continue;
            }
            $subcuentas[] = [
                'codigo' => $c->codigo,
                'codigo_maestro' => $c->codigoMaestro,
                'nombre' => $c->nombre,
                'tipo' => $c->tipo,
            ];
        }

        $cierre = $this->cierres->defectoDe($personaId);
        $meses = [];
        $stC = $this->pdo->prepare(
            'SELECT anio, mes, fecha_cierre FROM personal_cierre_mes WHERE persona_id = :p ORDER BY anio, mes'
        );
        $stC->execute([':p' => $personaId]);
        foreach ($stC->fetchAll() as $row) {
            $f = (new ConverterDate('date', $row['fecha_cierre']))->fromPg();
            if ($f === null) {
                continue;
            }
            $meses[] = [
                'anio' => (int) $row['anio'],
                'mes' => (int) $row['mes'],
                'fecha_cierre' => $f->format('Y-m-d'),
            ];
        }

        $banco = [];
        $stB = $this->pdo->prepare(
            'SELECT asiento_id, banco, huella, fecha, importe, concepto
             FROM banco_import_filas WHERE persona_id = :p'
        );
        $stB->execute([':p' => $personaId]);
        foreach ($stB->fetchAll() as $row) {
            $f = (new ConverterDate('date', $row['fecha']))->fromPg();
            $banco[] = [
                'asiento_ref' => (int) $row['asiento_id'],
                'banco' => (string) $row['banco'],
                'huella' => (string) $row['huella'],
                'fecha' => $f !== null ? $f->format('Y-m-d') : '',
                'importe' => (string) $row['importe'],
                'concepto' => (string) $row['concepto'],
            ];
        }

        return [
            'version' => self::VERSION,
            'exportado' => (new DateTimeImmutable())->format('c'),
            'centro_id' => $centroId,
            'persona_id' => $personaId,
            'iniciales' => $iniciales,
            'cierre' => [
                'dia_cierre' => $cierre['dia_cierre'],
                'dia_habil' => $cierre['dia_habil'],
                'meses' => $meses,
            ],
            'subcuentas' => $subcuentas,
            'asientos' => $asientos,
            'banco' => $banco,
        ];
    }

    public function restaurar(int $centroId, int $personaId, array $datos): void
    {
        $version = (int) ($datos['version'] ?? 0);
        if ($version !== self::VERSION) {
            throw new InvalidArgumentException('Versión de copia personal no soportada');
        }
        if ((int) ($datos['centro_id'] ?? 0) !== $centroId) {
            throw new InvalidArgumentException('La copia pertenece a otro centro');
        }
        if ((int) ($datos['persona_id'] ?? 0) !== $personaId) {
            throw new InvalidArgumentException('La copia pertenece a otra persona');
        }

        $this->asegurar->ejecutar($centroId, $personaId);
        $this->pdo->beginTransaction();
        try {
            $this->borrarMovimientos($personaId);
            $this->restaurarCierre($personaId, $datos['cierre'] ?? []);
            $this->restaurarSubcuentas($centroId, $personaId, $datos['subcuentas'] ?? []);
            $mapa = $this->restaurarAsientos($centroId, $personaId, $datos['asientos'] ?? []);
            $this->restaurarBanco($personaId, $datos['banco'] ?? [], $mapa);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function borrarMovimientos(int $personaId): void
    {
        $this->pdo->prepare(
            'UPDATE asientos SET asiento_par_id = NULL
             WHERE libro = \'X\' AND persona_id = :p'
        )->execute([':p' => $personaId]);
        $this->pdo->prepare(
            'DELETE FROM asientos WHERE libro = \'X\' AND persona_id = :p'
        )->execute([':p' => $personaId]);
    }

    /** @param array<string, mixed> $cierre */
    private function restaurarCierre(int $personaId, array $cierre): void
    {
        $dia = isset($cierre['dia_cierre']) && $cierre['dia_cierre'] !== ''
            ? (int) $cierre['dia_cierre'] : null;
        $habil = !empty($cierre['dia_habil']);
        $this->cierres->guardarDefecto($personaId, $dia, $habil);
        $this->pdo->prepare('DELETE FROM personal_cierre_mes WHERE persona_id = :p')
            ->execute([':p' => $personaId]);
        foreach ($cierre['meses'] ?? [] as $mes) {
            if (!is_array($mes)) {
                continue;
            }
            $anio = (int) ($mes['anio'] ?? 0);
            $m = (int) ($mes['mes'] ?? 0);
            $fechaRaw = trim((string) ($mes['fecha_cierre'] ?? ''));
            if ($anio < 2000 || $m < 1 || $m > 12 || $fechaRaw === '') {
                continue;
            }
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaRaw);
            if ($fecha === false) {
                continue;
            }
            $this->cierres->guardarMes($personaId, $anio, $m, $fecha);
        }
    }

    /** @param list<array<string, mixed>> $subcuentas */
    private function restaurarSubcuentas(int $centroId, int $personaId, array $subcuentas): void
    {
        foreach ($subcuentas as $sub) {
            if (!is_array($sub)) {
                continue;
            }
            $codigo = trim((string) ($sub['codigo'] ?? ''));
            if ($codigo === '' || $this->cuentas->buscar($centroId, $personaId, 'X', $codigo) !== null) {
                continue;
            }
            $maestro = trim((string) ($sub['codigo_maestro'] ?? ''));
            $padre = $this->cuentas->buscar($centroId, $personaId, 'X', $maestro);
            if ($padre === null || $padre->id === null) {
                continue;
            }
            $nombre = trim((string) ($sub['nombre'] ?? $codigo));
            $this->cuentas->guardar(new Cuenta(
                null,
                $centroId,
                $personaId,
                null,
                $padre->id,
                'X',
                $codigo,
                $nombre,
                'Restaurada de copia',
                (string) ($sub['tipo'] ?? $padre->tipo),
                $padre->naturaleza,
                $maestro,
                true,
                $padre->orden,
            ));
        }
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return array<int, int> ref antiguo => id nuevo
     */
    private function restaurarAsientos(int $centroId, int $personaId, array $filas): array
    {
        $mapa = [];
        $pendientesPar = [];
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ref = (int) ($fila['ref'] ?? 0);
            if ($ref <= 0) {
                continue;
            }
            $parRef = isset($fila['par_ref']) && $fila['par_ref'] !== null ? (int) $fila['par_ref'] : null;
            $asiento = $this->construirAsiento($centroId, $personaId, $fila, null);
            $guardado = $this->asientos->guardar($asiento, true);
            if ($guardado->id === null) {
                throw new RuntimeException('No se pudo guardar un movimiento personal');
            }
            $mapa[$ref] = $guardado->id;
            if ($parRef !== null) {
                $pendientesPar[] = ['id' => $guardado->id, 'par_ref' => $parRef];
            }
        }
        foreach ($pendientesPar as $item) {
            $parNuevo = $mapa[$item['par_ref']] ?? null;
            if ($parNuevo === null) {
                continue;
            }
            $this->asientos->enlazar($item['id'], $parNuevo);
        }

        return $mapa;
    }

    /** @param array<string, mixed> $fila */
    private function construirAsiento(
        int $centroId,
        int $personaId,
        array $fila,
        ?int $parId,
    ): Asiento {
        $fechaRaw = trim((string) ($fila['fecha'] ?? ''));
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaRaw);
        if ($fecha === false) {
            throw new InvalidArgumentException('Fecha de asiento no válida en la copia');
        }
        $fechaOpRaw = trim((string) ($fila['fecha_operacion'] ?? $fechaRaw));
        $fechaOp = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaOpRaw) ?: $fecha;
        $ejercicio = $this->ejercicios->deCentroEnFecha($centroId, $fecha);
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new InvalidArgumentException('No hay ejercicio para la fecha ' . $fechaRaw);
        }
        $movimientos = [];
        $orden = 1;
        foreach ($fila['lineas'] ?? [] as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            $codigo = trim((string) ($linea['cuenta_codigo'] ?? ''));
            $cuenta = $this->cuentas->buscar($centroId, $personaId, 'X', $codigo);
            if ($cuenta === null || $cuenta->id === null) {
                throw new InvalidArgumentException('Falta la cuenta personal ' . $codigo . ' al restaurar');
            }
            $movimientos[] = new Movimiento(
                null,
                $orden++,
                $cuenta->id,
                $personaId,
                (int) ($linea['debe_cents'] ?? 0),
                (int) ($linea['haber_cents'] ?? 0),
            );
        }
        if (count($movimientos) < 2) {
            throw new InvalidArgumentException('Asiento incompleto en la copia');
        }

        return new Asiento(
            null,
            $ejercicio->id,
            'X',
            null,
            $fecha,
            isset($fila['glosa']) ? (string) $fila['glosa'] : null,
            (string) ($fila['tipo'] ?? 'normal'),
            (string) ($fila['origen'] ?? 'manual'),
            $personaId,
            $movimientos,
            null,
            $parId,
            $fechaOp,
            null,
            !empty($fila['gasto_generales']),
            isset($fila['concepto_generales']) && $fila['concepto_generales'] !== ''
                ? (string) $fila['concepto_generales'] : null,
        );
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @param array<int, int> $mapa
     */
    private function restaurarBanco(int $personaId, array $filas, array $mapa): void
    {
        $ins = $this->pdo->prepare(
            'INSERT INTO banco_import_filas (persona_id, banco, huella, asiento_id, fecha, importe, concepto)
             VALUES (:p, :b, :h, :a, :f, :i, :c)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ref = (int) ($fila['asiento_ref'] ?? 0);
            $asientoId = $mapa[$ref] ?? null;
            if ($asientoId === null) {
                continue;
            }
            $ins->execute([
                ':p' => $personaId,
                ':b' => (string) ($fila['banco'] ?? ''),
                ':h' => (string) ($fila['huella'] ?? ''),
                ':a' => $asientoId,
                ':f' => (new ConverterDate('date', (string) ($fila['fecha'] ?? '')))->toPg(),
                ':i' => (string) ($fila['importe'] ?? ''),
                ':c' => (string) ($fila['concepto'] ?? ''),
            ]);
        }
    }

    /** @return array<string, Cuenta> codigo => cuenta */
    private function mapaCuentas(int $centroId, int $personaId): array
    {
        $out = [];
        foreach ($this->cuentas->listarDePersona($centroId, $personaId, 'X') as $c) {
            $out[$c->codigo] = $c;
        }

        return $out;
    }

    private function inicialesDe(int $personaId): string
    {
        $st = $this->pdo->prepare('SELECT iniciales FROM personas WHERE id = :id');
        $st->execute([':id' => $personaId]);

        return strtolower((string) ($st->fetchColumn() ?: 'p' . $personaId));
    }
}
