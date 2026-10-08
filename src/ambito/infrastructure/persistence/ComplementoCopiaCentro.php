<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\persistence;

use InvalidArgumentException;
use PDO;
use src\shared\infrastructure\persistence\ConverterDate;

/**
 * Resto del volcado de un centro, aparte de los asientos.
 * Las contraseñas y el libro personal de otro centro no entran aquí.
 */
final class ComplementoCopiaCentro
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function exportar(int $centroId): array
    {
        return [
            'centro' => $this->exportarCentro($centroId),
            'configuracion' => $this->exportarConfiguracion(),
            'ejercicios' => $this->exportarEjercicios($centroId),
            'cuentas_fisicas' => $this->exportarFisicas($centroId),
            'personas' => $this->exportarPersonas($centroId),
            'cuentas' => $this->exportarCuentas($centroId),
            'partidas_labores' => $this->exportarPartidas($centroId),
            'destinos_sg' => $this->exportarDestinos($centroId),
            'plantillas' => $this->exportarPlantillas($centroId),
            'remesas' => $this->exportarRemesas($centroId),
            'import_filas' => $this->exportarImportFilas($centroId),
            'import_ejecuciones' => $this->exportarImportEjecuciones($centroId),
            'grisbi' => $this->exportarGrisbi($centroId),
            'banco_centro' => $this->exportarBanco($centroId),
            'arqueos' => $this->exportarArqueos($centroId),
            'arqueos_cuadrados' => $this->exportarArqueosCuadrados($centroId),
            'informes_613' => $this->exportarInformes613($centroId),
            'entradas_periodicas' => $this->exportarEntradas($centroId),
            'labores' => $this->exportarLabores($centroId),
            'disponibles' => $this->exportarDisponibles($centroId),
            'envios_dl' => $this->exportarEnvios($centroId),
            'cierres_mes' => $this->exportarCierresMes($centroId),
            'banco_personal' => $this->exportarBancoPersonal($centroId),
        ];
    }

    /** @param array<string, mixed> $datos */
    public function antes(int $centroId, array $datos): void
    {
        if (is_array($datos['centro'] ?? null)) {
            $this->restaurarCentro($centroId, $datos['centro']);
        }
        if (is_array($datos['configuracion'] ?? null)) {
            $this->restaurarConfiguracion($datos['configuracion']);
        }
        if (array_key_exists('ejercicios', $datos)) {
            $this->restaurarEjercicios($centroId, $datos['ejercicios']);
        }
        if (array_key_exists('cuentas_fisicas', $datos)) {
            $this->restaurarFisicas($centroId, $datos['cuentas_fisicas']);
        }
        if (array_key_exists('personas', $datos)) {
            $this->restaurarPersonas($centroId, $datos['personas']);
        }
        if (array_key_exists('cuentas', $datos)) {
            $this->restaurarCuentas($centroId, $datos['cuentas']);
        }
        if (array_key_exists('partidas_labores', $datos)) {
            $this->restaurarPartidas($centroId, $datos['partidas_labores']);
        }
        if (array_key_exists('destinos_sg', $datos)) {
            $this->restaurarDestinos($centroId, $datos['destinos_sg']);
        }
    }

    /**
     * Plantillas y remesas, cuando los asientos viejos ya no las referencian.
     *
     * @param array<string, mixed> $datos
     * @return array{plantillas: array<string, int>, remesas: array<int, int>}
     */
    public function trasBorrarAsientos(int $centroId, array $datos): array
    {
        $plantillas = array_key_exists('plantillas', $datos)
            ? $this->restaurarPlantillas($centroId, $datos['plantillas'])
            : [];
        $remesas = array_key_exists('remesas', $datos)
            ? $this->restaurarRemesas($centroId, $datos['remesas'])
            : [];

        return ['plantillas' => $plantillas, 'remesas' => $remesas];
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<int, int> $asientos ref antigua => id nuevo
     * @param array<int, int> $remesas
     */
    public function trasCrearAsientos(int $centroId, array $datos, array $asientos, array $remesas): void
    {
        if (array_key_exists('import_filas', $datos)) {
            $this->restaurarImportFilas($centroId, $datos['import_filas'], $asientos);
        }
        if (array_key_exists('import_ejecuciones', $datos)) {
            $this->restaurarImportEjecuciones($centroId, $datos['import_ejecuciones']);
        }
        if (array_key_exists('grisbi', $datos)) {
            $this->restaurarGrisbi($centroId, $datos['grisbi'], $asientos);
        }
        if (array_key_exists('banco_centro', $datos)) {
            $this->restaurarBanco($centroId, $datos['banco_centro'], $asientos);
        }
        if (array_key_exists('arqueos', $datos)) {
            $this->restaurarArqueos($centroId, $datos['arqueos']);
        }
        if (array_key_exists('arqueos_cuadrados', $datos)) {
            $this->restaurarArqueosCuadrados($centroId, $datos['arqueos_cuadrados']);
        }
        if (array_key_exists('informes_613', $datos)) {
            $this->restaurarInformes613($centroId, $datos['informes_613']);
        }
        if (array_key_exists('entradas_periodicas', $datos)) {
            $this->restaurarEntradas($centroId, $datos['entradas_periodicas']);
        }
        $labores = [];
        if (array_key_exists('labores', $datos)) {
            $labores = $this->restaurarLabores($centroId, $datos['labores'], $remesas);
        }
        if (array_key_exists('disponibles', $datos)) {
            $this->restaurarDisponibles($centroId, $datos['disponibles'], $remesas, $labores);
        }
        if (array_key_exists('envios_dl', $datos)) {
            $this->restaurarEnvios($centroId, $datos['envios_dl']);
        }
        if (array_key_exists('cierres_mes', $datos)) {
            $this->restaurarCierresMes($centroId, $datos['cierres_mes']);
        }
        if (array_key_exists('banco_personal', $datos)) {
            $this->restaurarBancoPersonal($centroId, $datos['banco_personal'], $asientos);
        }
    }

    /** @return array<string, mixed> */
    private function exportarCentro(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.nombre, c.tipo, c.tipo_cierre, c.num_s, c.banco_csv, c.desgravacion_tramos_json,
                    p.codigo AS plan_codigo
             FROM centros c
             LEFT JOIN planes_contables p ON p.id = c.plan_contable_id
             WHERE c.id = :id'
        );
        $st->execute([':id' => $centroId]);
        $row = $st->fetch();
        if (!is_array($row)) {
            throw new InvalidArgumentException(_('Centro no encontrado'));
        }

        return [
            'nombre' => (string) $row['nombre'],
            'tipo' => (string) $row['tipo'],
            'tipo_cierre' => (string) $row['tipo_cierre'],
            'num_s' => (int) $row['num_s'],
            'banco_csv' => $row['banco_csv'] !== null ? (string) $row['banco_csv'] : null,
            'desgravacion_tramos' => $this->json($row['desgravacion_tramos_json']),
            'plan_contable' => $row['plan_codigo'] !== null ? (string) $row['plan_codigo'] : null,
        ];
    }

    /** @param array<string, mixed> $fila */
    private function restaurarCentro(int $centroId, array $fila): void
    {
        $st = $this->pdo->prepare(
            'UPDATE centros SET nombre = :n, tipo = :tipo, tipo_cierre = :cierre, num_s = :num,
                    banco_csv = :banco, desgravacion_tramos_json = :tramos
             WHERE id = :id'
        );
        $st->execute([
            ':n' => (string) ($fila['nombre'] ?? ''),
            ':tipo' => (string) ($fila['tipo'] ?? 'n'),
            ':cierre' => (string) ($fila['tipo_cierre'] ?? 'vivienda'),
            ':num' => (int) ($fila['num_s'] ?? 0),
            ':banco' => isset($fila['banco_csv']) && $fila['banco_csv'] !== '' ? (string) $fila['banco_csv'] : null,
            ':tramos' => json_encode($fila['desgravacion_tramos'] ?? [], JSON_UNESCAPED_UNICODE),
            ':id' => $centroId,
        ]);
        $plan = isset($fila['plan_contable']) ? trim((string) $fila['plan_contable']) : '';
        if ($plan === '') {
            return;
        }
        $stPlan = $this->pdo->prepare('SELECT id FROM planes_contables WHERE codigo = :c');
        $stPlan->execute([':c' => $plan]);
        $planId = $stPlan->fetchColumn();
        if ($planId === false) {
            throw new InvalidArgumentException(sprintf(_('No está el plan contable «%s»'), $plan));
        }
        $this->pdo->prepare('UPDATE centros SET plan_contable_id = :p WHERE id = :id')
            ->execute([':p' => (int) $planId, ':id' => $centroId]);
    }

    /** @return array<string, mixed>|null */
    private function exportarConfiguracion(): ?array
    {
        $row = $this->pdo->query('SELECT * FROM configuracion WHERE id = 1')->fetch();
        if (!is_array($row)) {
            return null;
        }
        $ini = $this->fecha($row['fecha_inicio']);
        $cie = $this->fecha($row['fecha_cierre']);
        if ($ini === null || $cie === null) {
            return null;
        }

        return [
            'centro' => (string) $row['centro'],
            'anio' => (int) $row['anio'],
            'modo_ejercicio' => (string) $row['modo_ejercicio'],
            'fecha_inicio' => $ini,
            'fecha_cierre' => $cie,
            'tipo_cierre' => (string) $row['tipo_cierre'],
            'num_residentes' => $row['num_residentes'] !== null ? (int) $row['num_residentes'] : null,
            'version' => $row['version'] !== null ? (string) $row['version'] : null,
        ];
    }

    /** @param array<string, mixed> $fila */
    private function restaurarConfiguracion(array $fila): void
    {
        $ini = $this->fecha($fila['fecha_inicio'] ?? null);
        $cie = $this->fecha($fila['fecha_cierre'] ?? null);
        if ($ini === null || $cie === null) {
            return;
        }
        $st = $this->pdo->prepare(
            'UPDATE configuracion SET centro = :centro, anio = :anio, modo_ejercicio = :modo,
                    fecha_inicio = :ini, fecha_cierre = :cie, tipo_cierre = :tipo,
                    num_residentes = :num, version = :ver, updated_at = now()
             WHERE id = 1'
        );
        $st->execute([
            ':centro' => (string) ($fila['centro'] ?? ''),
            ':anio' => (int) ($fila['anio'] ?? 0),
            ':modo' => (string) ($fila['modo_ejercicio'] ?? 'Año'),
            ':ini' => $ini,
            ':cie' => $cie,
            ':tipo' => (string) ($fila['tipo_cierre'] ?? 'vivienda'),
            ':num' => $fila['num_residentes'] ?? null,
            ':ver' => $fila['version'] ?? null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function exportarEjercicios(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT e.etiqueta, e.fecha_inicio, e.fecha_fin, e.fecha_corte, e.estado,
                    a.fecha_inicio AS anterior
             FROM ejercicios e
             LEFT JOIN ejercicios a ON a.id = e.ejercicio_anterior_id
             WHERE e.centro_id = :c
             ORDER BY e.fecha_inicio'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $inicio = $this->fecha($row['fecha_inicio']);
            $fin = $this->fecha($row['fecha_fin']);
            $corte = $this->fecha($row['fecha_corte']);
            if ($inicio === null || $fin === null || $corte === null) {
                continue;
            }
            $out[] = [
                'etiqueta' => (string) $row['etiqueta'],
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
                'fecha_corte' => $corte,
                'estado' => (string) $row['estado'],
                'anterior' => $this->fecha($row['anterior']),
            ];
        }

        return $out;
    }

    private function restaurarEjercicios(int $centroId, mixed $filas): void
    {
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO ejercicios (centro_id, etiqueta, fecha_inicio, fecha_fin, fecha_corte, estado)
             VALUES (:c, :et, :ini, :fin, :corte, :estado)
             ON CONFLICT (centro_id, fecha_inicio) DO UPDATE SET
                etiqueta = excluded.etiqueta, fecha_fin = excluded.fecha_fin,
                fecha_corte = excluded.fecha_corte, estado = excluded.estado'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $inicio = $this->fecha($fila['fecha_inicio'] ?? null);
            $fin = $this->fecha($fila['fecha_fin'] ?? null);
            $corte = $this->fecha($fila['fecha_corte'] ?? null);
            if ($inicio === null || $fin === null || $corte === null) {
                continue;
            }
            $st->execute([
                ':c' => $centroId,
                ':et' => (string) ($fila['etiqueta'] ?? $inicio),
                ':ini' => $inicio,
                ':fin' => $fin,
                ':corte' => $corte,
                ':estado' => (string) ($fila['estado'] ?? 'abierto'),
            ]);
        }
        $enlace = $this->pdo->prepare(
            'UPDATE ejercicios SET ejercicio_anterior_id = :ant
             WHERE centro_id = :c AND fecha_inicio = :ini'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $inicio = $this->fecha($fila['fecha_inicio'] ?? null);
            $anterior = $this->fecha($fila['anterior'] ?? null);
            if ($inicio === null) {
                continue;
            }
            $enlace->execute([
                ':ant' => $anterior !== null ? $this->ejercicioId($centroId, $anterior) : null,
                ':c' => $centroId,
                ':ini' => $inicio,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarFisicas(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT tipo, nombre, iban, orden, activo FROM cuentas_fisicas
             WHERE centro_id = :c ORDER BY orden, nombre'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'tipo' => (string) $row['tipo'],
                'nombre' => (string) $row['nombre'],
                'iban' => $row['iban'] !== null ? (string) $row['iban'] : null,
                'orden' => (int) $row['orden'],
                'activo' => self::booleano($row['activo']),
            ];
        }

        return $out;
    }

    private function restaurarFisicas(int $centroId, mixed $filas): void
    {
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO cuentas_fisicas (centro_id, tipo, nombre, iban, orden, activo)
             VALUES (:c, :t, :n, :i, :o, :a)
             ON CONFLICT (centro_id, nombre) DO UPDATE SET
                tipo = excluded.tipo, iban = excluded.iban, orden = excluded.orden, activo = excluded.activo'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila) || trim((string) ($fila['nombre'] ?? '')) === '') {
                continue;
            }
            $st->execute([
                ':c' => $centroId,
                ':t' => (string) ($fila['tipo'] ?? 'caja'),
                ':n' => (string) $fila['nombre'],
                ':i' => isset($fila['iban']) && $fila['iban'] !== '' ? (string) $fila['iban'] : null,
                ':o' => (int) ($fila['orden'] ?? 0),
                ':a' => self::booleano($fila['activo'] ?? true) ? 1 : 0,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarPersonas(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT p.*, s.grupo, s.clase
             FROM personas p
             LEFT JOIN persona_sg s ON s.persona_id = p.id
             WHERE p.centro_id = :c
             ORDER BY p.orden, lower(p.iniciales)'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'nombre' => (string) $row['nombre'],
                'apellidos' => (string) $row['apellidos'],
                'iniciales' => strtolower((string) $row['iniciales']),
                'orden' => (int) $row['orden'],
                'activo' => self::booleano($row['activo']),
                'email' => $row['email'] !== null && $row['email'] !== '' ? (string) $row['email'] : null,
                'mes_exento_inicio' => $row['mes_exento_inicio'] !== null ? (int) $row['mes_exento_inicio'] : null,
                'mes_exento_fin' => $row['mes_exento_fin'] !== null ? (int) $row['mes_exento_fin'] : null,
                'mes_exento2_inicio' => $row['mes_exento2_inicio'] !== null ? (int) $row['mes_exento2_inicio'] : null,
                'mes_exento2_fin' => $row['mes_exento2_fin'] !== null ? (int) $row['mes_exento2_fin'] : null,
                'importe_vivienda_fijo' => $row['importe_vivienda_fijo'] !== null ? (string) $row['importe_vivienda_fijo'] : null,
                'vivienda_aporta_generales' => self::booleano($row['vivienda_aporta_generales']),
                'puede_desgravar' => self::booleano($row['puede_desgravar']),
                'base_liquidable' => $row['base_liquidable'] !== null ? (string) $row['base_liquidable'] : null,
                'remanente_cents' => (int) $row['remanente_cents'],
                'dia_cierre' => $row['dia_cierre'] !== null ? (int) $row['dia_cierre'] : null,
                'cierre_dia_habil' => self::booleano($row['cierre_dia_habil']),
                'banco_csv' => $row['banco_csv'] !== null ? (string) $row['banco_csv'] : null,
                'grupo' => $row['grupo'] !== null ? (int) $row['grupo'] : null,
                'clase' => $row['clase'] !== null ? (string) $row['clase'] : null,
            ];
        }

        return $out;
    }

    private function restaurarPersonas(int $centroId, mixed $filas): void
    {
        if (!is_array($filas)) {
            return;
        }
        $presentes = [];
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $iniciales = strtolower(trim((string) ($fila['iniciales'] ?? '')));
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            if ($iniciales === '' || $nombre === '') {
                continue;
            }
            $presentes[] = $iniciales;
            $id = $this->personaId($centroId, $iniciales);
            $params = [
                ':nom' => $nombre,
                ':ape' => (string) ($fila['apellidos'] ?? ''),
                ':ini' => $iniciales,
                ':ord' => (int) ($fila['orden'] ?? 0),
                ':act' => self::booleano($fila['activo'] ?? true) ? 1 : 0,
                ':email' => isset($fila['email']) && $fila['email'] !== '' ? (string) $fila['email'] : null,
                ':ex1' => $fila['mes_exento_inicio'] ?? null,
                ':ex2' => $fila['mes_exento_fin'] ?? null,
                ':ex3' => $fila['mes_exento2_inicio'] ?? null,
                ':ex4' => $fila['mes_exento2_fin'] ?? null,
                ':viv' => $fila['importe_vivienda_fijo'] ?? null,
                ':aporta' => self::booleano($fila['vivienda_aporta_generales'] ?? true) ? 1 : 0,
                ':desg' => self::booleano($fila['puede_desgravar'] ?? true) ? 1 : 0,
                ':base' => $fila['base_liquidable'] ?? null,
                ':rem' => (int) ($fila['remanente_cents'] ?? 0),
                ':dia' => $fila['dia_cierre'] ?? null,
                ':habil' => self::booleano($fila['cierre_dia_habil'] ?? false) ? 1 : 0,
                ':banco' => $fila['banco_csv'] ?? null,
            ];
            if ($id === null) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO personas (
                        nombre, apellidos, iniciales, orden, centro_id, activo, email,
                        mes_exento_inicio, mes_exento_fin, mes_exento2_inicio, mes_exento2_fin,
                        importe_vivienda_fijo, vivienda_aporta_generales, puede_desgravar, base_liquidable,
                        remanente_cents, dia_cierre, cierre_dia_habil, banco_csv
                     ) VALUES (
                        :nom, :ape, :ini, :ord, :c, :act, :email,
                        :ex1, :ex2, :ex3, :ex4,
                        :viv, :aporta, :desg, :base,
                        :rem, :dia, :habil, :banco
                     ) RETURNING id'
                );
                $ins->execute($params + [':c' => $centroId]);
                $id = (int) $ins->fetchColumn();
            } else {
                $upd = $this->pdo->prepare(
                    'UPDATE personas SET nombre = :nom, apellidos = :ape, iniciales = :ini, orden = :ord,
                        activo = :act, email = :email, mes_exento_inicio = :ex1, mes_exento_fin = :ex2,
                        mes_exento2_inicio = :ex3, mes_exento2_fin = :ex4, importe_vivienda_fijo = :viv,
                        vivienda_aporta_generales = :aporta, puede_desgravar = :desg, base_liquidable = :base,
                        remanente_cents = :rem, dia_cierre = :dia, cierre_dia_habil = :habil, banco_csv = :banco
                     WHERE id = :id'
                );
                $upd->execute($params + [':id' => $id]);
            }
            $this->pdo->prepare('DELETE FROM persona_sg WHERE persona_id = :id')->execute([':id' => $id]);
            $clase = $fila['clase'] ?? null;
            if ($clase === 's' || $clase === 'cp') {
                $this->pdo->prepare(
                    'INSERT INTO persona_sg (persona_id, grupo, clase) VALUES (:id, :g, :cl)'
                )->execute([
                    ':id' => $id,
                    ':g' => max(1, (int) ($fila['grupo'] ?? 1)),
                    ':cl' => $clase,
                ]);
            }
        }
        if ($presentes === []) {
            $this->pdo->prepare('UPDATE personas SET activo = FALSE WHERE centro_id = :c')
                ->execute([':c' => $centroId]);

            return;
        }
        $marcas = implode(', ', array_fill(0, count($presentes), '?'));
        $st = $this->pdo->prepare(
            "UPDATE personas SET activo = FALSE WHERE centro_id = ? AND lower(iniciales) NOT IN ($marcas)"
        );
        $st->execute(array_merge([$centroId], $presentes));
    }

    /** @return list<array<string, mixed>> */
    private function exportarCuentas(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.libro, c.codigo, c.nombre, c.descripcion, c.tipo, c.naturaleza, c.codigo_maestro,
                    c.imputable, c.orden, c.activo, p.iniciales, f.nombre AS fisica,
                    cp.libro AS padre_libro, cp.codigo AS padre_codigo, pp.iniciales AS padre_iniciales
             FROM cuentas c
             LEFT JOIN personas p ON p.id = c.persona_id
             LEFT JOIN cuentas_fisicas f ON f.id = c.cuenta_fisica_id
             LEFT JOIN cuentas cp ON cp.id = c.padre_id
             LEFT JOIN personas pp ON pp.id = cp.persona_id
             WHERE c.centro_id = :c
             ORDER BY c.libro, c.codigo'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'libro' => (string) $row['libro'],
                'codigo' => (string) $row['codigo'],
                'iniciales' => $row['iniciales'] !== null ? strtolower((string) $row['iniciales']) : '',
                'nombre' => (string) $row['nombre'],
                'descripcion' => (string) $row['descripcion'],
                'tipo' => (string) $row['tipo'],
                'naturaleza' => (string) $row['naturaleza'],
                'codigo_maestro' => (string) $row['codigo_maestro'],
                'imputable' => self::booleano($row['imputable']),
                'orden' => (int) $row['orden'],
                'activo' => self::booleano($row['activo']),
                'fisica' => $row['fisica'] !== null ? (string) $row['fisica'] : null,
                'padre_libro' => $row['padre_libro'] !== null ? (string) $row['padre_libro'] : null,
                'padre_codigo' => $row['padre_codigo'] !== null ? (string) $row['padre_codigo'] : null,
                'padre_iniciales' => $row['padre_iniciales'] !== null ? strtolower((string) $row['padre_iniciales']) : '',
            ];
        }

        return $out;
    }

    private function restaurarCuentas(int $centroId, mixed $filas): void
    {
        if (!is_array($filas)) {
            return;
        }
        $upd = $this->pdo->prepare(
            'UPDATE cuentas SET nombre = :nom, descripcion = :des, tipo = :tipo, naturaleza = :nat,
                    codigo_maestro = :mae, imputable = :imp, orden = :ord, activo = :act,
                    cuenta_fisica_id = :fis, persona_id = :per
             WHERE id = :id'
        );
        $ins = $this->pdo->prepare(
            'INSERT INTO cuentas (
                centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo, nombre, descripcion,
                tipo, naturaleza, codigo_maestro, imputable, orden, activo
             ) VALUES (
                :c, :per, :fis, NULL, :lib, :cod, :nom, :des, :tipo, :nat, :mae, :imp, :ord, :act
             )'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $libro = (string) ($fila['libro'] ?? '');
            $codigo = (string) ($fila['codigo'] ?? '');
            if ($libro === '' || $codigo === '') {
                continue;
            }
            $iniciales = strtolower(trim((string) ($fila['iniciales'] ?? '')));
            $personaId = $iniciales !== '' ? $this->exigirPersona($centroId, $iniciales) : null;
            $fisicaId = isset($fila['fisica']) && $fila['fisica'] !== ''
                ? $this->fisicaId($centroId, (string) $fila['fisica']) : null;
            $comun = [
                ':nom' => (string) ($fila['nombre'] ?? $codigo),
                ':des' => (string) ($fila['descripcion'] ?? ''),
                ':tipo' => (string) ($fila['tipo'] ?? 'gasto'),
                ':nat' => (string) ($fila['naturaleza'] ?? 'deudora'),
                ':mae' => (string) ($fila['codigo_maestro'] ?? $codigo),
                ':imp' => self::booleano($fila['imputable'] ?? true) ? 1 : 0,
                ':ord' => (int) ($fila['orden'] ?? 0),
                ':act' => self::booleano($fila['activo'] ?? true) ? 1 : 0,
                ':fis' => $fisicaId,
                ':per' => $personaId,
            ];
            $id = $this->cuentaId($centroId, $libro, $codigo, $iniciales);
            if ($id === null) {
                $ins->execute($comun + [':c' => $centroId, ':lib' => $libro, ':cod' => $codigo]);
            } else {
                $upd->execute($comun + [':id' => $id]);
            }
        }
        $padre = $this->pdo->prepare('UPDATE cuentas SET padre_id = :p WHERE id = :id');
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $libro = (string) ($fila['libro'] ?? '');
            $codigo = (string) ($fila['codigo'] ?? '');
            $iniciales = strtolower(trim((string) ($fila['iniciales'] ?? '')));
            $id = $this->cuentaId($centroId, $libro, $codigo, $iniciales);
            if ($id === null) {
                continue;
            }
            $padreLibro = (string) ($fila['padre_libro'] ?? '');
            $padreCodigo = (string) ($fila['padre_codigo'] ?? '');
            $padreId = null;
            if ($padreLibro !== '' && $padreCodigo !== '') {
                $padreId = $this->cuentaId(
                    $centroId,
                    $padreLibro,
                    $padreCodigo,
                    strtolower(trim((string) ($fila['padre_iniciales'] ?? ''))),
                );
            }
            $padre->execute([':p' => $padreId, ':id' => $id]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarPartidas(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT codigo, etiqueta, orden, activo, desgrava FROM centro_partidas_labores
             WHERE centro_id = :c ORDER BY orden, codigo'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'codigo' => (string) $row['codigo'],
                'etiqueta' => (string) $row['etiqueta'],
                'orden' => (int) $row['orden'],
                'activo' => self::booleano($row['activo']),
                'desgrava' => self::booleano($row['desgrava']),
            ];
        }

        return $out;
    }

    private function restaurarPartidas(int $centroId, mixed $filas): void
    {
        $this->pdo->prepare('DELETE FROM centro_partidas_labores WHERE centro_id = :c')->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO centro_partidas_labores (centro_id, codigo, etiqueta, orden, activo, desgrava)
             VALUES (:c, :cod, :et, :o, :a, :d)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila) || trim((string) ($fila['codigo'] ?? '')) === '') {
                continue;
            }
            $st->execute([
                ':c' => $centroId,
                ':cod' => (string) $fila['codigo'],
                ':et' => (string) ($fila['etiqueta'] ?? $fila['codigo']),
                ':o' => (int) ($fila['orden'] ?? 0),
                ':a' => self::booleano($fila['activo'] ?? true) ? 1 : 0,
                ':d' => self::booleano($fila['desgrava'] ?? false) ? 1 : 0,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarDestinos(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT codigo, etiqueta, orden FROM centro_destinos_sg WHERE centro_id = :c ORDER BY orden, codigo'
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

    private function restaurarDestinos(int $centroId, mixed $filas): void
    {
        $this->pdo->prepare('DELETE FROM centro_destinos_sg WHERE centro_id = :c')->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO centro_destinos_sg (centro_id, codigo, etiqueta, orden) VALUES (:c, :cod, :et, :o)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila) || trim((string) ($fila['codigo'] ?? '')) === '') {
                continue;
            }
            $st->execute([
                ':c' => $centroId,
                ':cod' => (string) $fila['codigo'],
                ':et' => (string) ($fila['etiqueta'] ?? $fila['codigo']),
                ':o' => (int) ($fila['orden'] ?? 0),
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarPlantillas(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, cuenta, nombre, activa, orden FROM plantillas_apunte
             WHERE centro_id = :c ORDER BY orden, nombre'
        );
        $st->execute([':c' => $centroId]);
        $lineas = $this->pdo->prepare(
            'SELECT orden, origen, concepto_codigo, observaciones, cantidad, cuenta
             FROM plantilla_lineas_apunte WHERE plantilla_id = :id ORDER BY orden'
        );
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $lineas->execute([':id' => (int) $row['id']]);
            $movs = [];
            foreach ($lineas->fetchAll() as $lin) {
                $movs[] = [
                    'orden' => (int) $lin['orden'],
                    'origen' => trim((string) $lin['origen']),
                    'concepto' => trim((string) $lin['concepto_codigo']),
                    'observaciones' => $lin['observaciones'] !== null ? (string) $lin['observaciones'] : null,
                    'cantidad' => $lin['cantidad'] !== null ? (string) $lin['cantidad'] : null,
                    'cuenta' => trim((string) $lin['cuenta']),
                ];
            }
            $out[] = [
                'cuenta' => trim((string) $row['cuenta']),
                'nombre' => trim((string) $row['nombre']),
                'activa' => self::booleano($row['activa']),
                'orden' => (int) $row['orden'],
                'lineas' => $movs,
            ];
        }

        return $out;
    }

    /** @return array<string, int> */
    private function restaurarPlantillas(int $centroId, mixed $filas): array
    {
        $this->pdo->prepare('DELETE FROM plantillas_apunte WHERE centro_id = :c')->execute([':c' => $centroId]);
        $mapa = [];
        if (!is_array($filas)) {
            return $mapa;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO plantillas_apunte (centro_id, cuenta, nombre, activa, orden)
             VALUES (:c, :cu, :n, :a, :o) RETURNING id'
        );
        $lin = $this->pdo->prepare(
            'INSERT INTO plantilla_lineas_apunte (plantilla_id, orden, origen, concepto_codigo, observaciones, cantidad, cuenta)
             VALUES (:p, :o, :ori, :con, :obs, :cant, :cu)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila) || trim((string) ($fila['nombre'] ?? '')) === '') {
                continue;
            }
            $cuenta = strtoupper(trim((string) ($fila['cuenta'] ?? 'P')));
            $nombre = trim((string) $fila['nombre']);
            $ins->execute([
                ':c' => $centroId,
                ':cu' => $cuenta,
                ':n' => $nombre,
                ':a' => self::booleano($fila['activa'] ?? true) ? 1 : 0,
                ':o' => (int) ($fila['orden'] ?? 0),
            ]);
            $id = (int) $ins->fetchColumn();
            $mapa[$cuenta . '|' . strtolower($nombre)] = $id;
            foreach ($fila['lineas'] ?? [] as $mov) {
                if (!is_array($mov)) {
                    continue;
                }
                $lin->execute([
                    ':p' => $id,
                    ':o' => (int) ($mov['orden'] ?? 1),
                    ':ori' => (string) ($mov['origen'] ?? 'A'),
                    ':con' => (string) ($mov['concepto'] ?? ''),
                    ':obs' => $mov['observaciones'] ?? null,
                    ':cant' => $mov['cantidad'] ?? null,
                    ':cu' => (string) ($mov['cuenta'] ?? $cuenta),
                ]);
            }
        }

        return $mapa;
    }

    /** @return list<array<string, mixed>> */
    private function exportarRemesas(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT r.*, e.fecha_inicio, p.iniciales
             FROM remesas r
             JOIN ejercicios e ON e.id = r.ejercicio_id
             JOIN personas p ON p.id = r.persona_id
             WHERE r.centro_id = :c
             ORDER BY r.anio, r.mes, r.version, r.id'
        );
        $st->execute([':c' => $centroId]);
        $lineas = $this->pdo->prepare(
            'SELECT codigo_maestro, importe, detalle_json FROM remesa_lineas WHERE remesa_id = :id ORDER BY codigo_maestro'
        );
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $lineas->execute([':id' => (int) $row['id']]);
            $movs = [];
            foreach ($lineas->fetchAll() as $lin) {
                $movs[] = [
                    'codigo' => (string) $lin['codigo_maestro'],
                    'importe' => (int) $lin['importe'],
                    'detalle' => $this->json($lin['detalle_json']),
                ];
            }
            $out[] = [
                'ref' => (int) $row['id'],
                'ejercicio' => (string) $this->fecha($row['fecha_inicio']),
                'iniciales' => strtolower((string) $row['iniciales']),
                'anio' => (int) $row['anio'],
                'mes' => (int) $row['mes'],
                'version' => (int) $row['version'],
                'estado' => (string) $row['estado'],
                'hash' => (string) $row['hash_contenido'],
                'nota' => $row['nota'] !== null ? (string) $row['nota'] : null,
                'saldo_tesoreria_cents' => $row['saldo_tesoreria_cents'] !== null ? (int) $row['saldo_tesoreria_cents'] : null,
                'mensaje_xml' => $row['mensaje_xml'] !== null ? (string) $row['mensaje_xml'] : null,
                'enviada_at' => $row['enviada_at'] !== null ? (string) $row['enviada_at'] : null,
                'resuelta_at' => $row['resuelta_at'] !== null ? (string) $row['resuelta_at'] : null,
                'lineas' => $movs,
            ];
        }

        return $out;
    }

    /** @return array<int, int> */
    private function restaurarRemesas(int $centroId, mixed $filas): array
    {
        $this->pdo->prepare('DELETE FROM remesas WHERE centro_id = :c')->execute([':c' => $centroId]);
        $mapa = [];
        if (!is_array($filas)) {
            return $mapa;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO remesas (
                persona_id, centro_id, ejercicio_id, anio, mes, version, estado, hash_contenido,
                nota, saldo_tesoreria_cents, mensaje_xml, enviada_at, resuelta_at
             ) VALUES (
                :p, :c, :e, :anio, :mes, :ver, :est, :hash, :nota, :saldo, :xml, :env, :res
             ) RETURNING id'
        );
        $lin = $this->pdo->prepare(
            'INSERT INTO remesa_lineas (remesa_id, codigo_maestro, importe, detalle_json)
             VALUES (:r, :cod, :imp, :det)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $iniciales = strtolower(trim((string) ($fila['iniciales'] ?? '')));
            $ins->execute([
                ':p' => $this->exigirPersona($centroId, $iniciales),
                ':c' => $centroId,
                ':e' => $this->exigirEjercicio($centroId, (string) ($fila['ejercicio'] ?? '')),
                ':anio' => (int) ($fila['anio'] ?? 0),
                ':mes' => (int) ($fila['mes'] ?? 0),
                ':ver' => (int) ($fila['version'] ?? 1),
                ':est' => (string) ($fila['estado'] ?? 'borrador'),
                ':hash' => (string) ($fila['hash'] ?? ''),
                ':nota' => $fila['nota'] ?? null,
                ':saldo' => $fila['saldo_tesoreria_cents'] ?? null,
                ':xml' => $fila['mensaje_xml'] ?? null,
                ':env' => $fila['enviada_at'] ?? null,
                ':res' => $fila['resuelta_at'] ?? null,
            ]);
            $id = (int) $ins->fetchColumn();
            $ref = (int) ($fila['ref'] ?? 0);
            if ($ref > 0) {
                $mapa[$ref] = $id;
            }
            foreach ($fila['lineas'] ?? [] as $mov) {
                if (!is_array($mov)) {
                    continue;
                }
                $lin->execute([
                    ':r' => $id,
                    ':cod' => (string) ($mov['codigo'] ?? ''),
                    ':imp' => (int) ($mov['importe'] ?? 0),
                    ':det' => json_encode($mov['detalle'] ?? [], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        return $mapa;
    }

    /** @return list<array<string, mixed>> */
    private function exportarImportFilas(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT e.fecha_inicio, f.hoja, f.fila, f.hash_contenido, f.asiento_id
             FROM import_filas f
             JOIN ejercicios e ON e.id = f.ejercicio_id
             WHERE e.centro_id = :c
             ORDER BY e.fecha_inicio, f.hoja, f.fila'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'ejercicio' => (string) $this->fecha($row['fecha_inicio']),
                'hoja' => (string) $row['hoja'],
                'fila' => (int) $row['fila'],
                'hash' => (string) $row['hash_contenido'],
                'asiento_ref' => $row['asiento_id'] !== null ? (int) $row['asiento_id'] : null,
            ];
        }

        return $out;
    }

    /** @param array<int, int> $asientos */
    private function restaurarImportFilas(int $centroId, mixed $filas, array $asientos): void
    {
        $this->pdo->prepare(
            'DELETE FROM import_filas WHERE ejercicio_id IN (SELECT id FROM ejercicios WHERE centro_id = :c)'
        )->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO import_filas (ejercicio_id, hoja, fila, hash_contenido, asiento_id)
             VALUES (:e, :h, :f, :hash, :a)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ref = isset($fila['asiento_ref']) && $fila['asiento_ref'] !== null ? (int) $fila['asiento_ref'] : 0;
            $st->execute([
                ':e' => $this->exigirEjercicio($centroId, (string) ($fila['ejercicio'] ?? '')),
                ':h' => (string) ($fila['hoja'] ?? ''),
                ':f' => (int) ($fila['fila'] ?? 0),
                ':hash' => (string) ($fila['hash'] ?? ''),
                ':a' => $ref > 0 ? ($asientos[$ref] ?? null) : null,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarImportEjecuciones(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT e.fecha_inicio, x.fichero, x.sha256, x.dry_run, x.altas, x.cambios, x.bajas, x.omitidos, x.created_at
             FROM import_ejecuciones x
             JOIN ejercicios e ON e.id = x.ejercicio_id
             WHERE x.centro_id = :c
             ORDER BY x.created_at, x.id'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'ejercicio' => (string) $this->fecha($row['fecha_inicio']),
                'fichero' => (string) $row['fichero'],
                'sha256' => (string) $row['sha256'],
                'dry_run' => self::booleano($row['dry_run']),
                'altas' => (int) $row['altas'],
                'cambios' => (int) $row['cambios'],
                'bajas' => (int) $row['bajas'],
                'omitidos' => (int) $row['omitidos'],
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $out;
    }

    private function restaurarImportEjecuciones(int $centroId, mixed $filas): void
    {
        $this->pdo->prepare('DELETE FROM import_ejecuciones WHERE centro_id = :c')->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO import_ejecuciones (
                centro_id, ejercicio_id, fichero, sha256, dry_run, altas, cambios, bajas, omitidos, created_at
             ) VALUES (:c, :e, :f, :s, :d, :a, :cam, :b, :o, :crea)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $st->execute([
                ':c' => $centroId,
                ':e' => $this->exigirEjercicio($centroId, (string) ($fila['ejercicio'] ?? '')),
                ':f' => (string) ($fila['fichero'] ?? ''),
                ':s' => (string) ($fila['sha256'] ?? ''),
                ':d' => self::booleano($fila['dry_run'] ?? false) ? 1 : 0,
                ':a' => (int) ($fila['altas'] ?? 0),
                ':cam' => (int) ($fila['cambios'] ?? 0),
                ':b' => (int) ($fila['bajas'] ?? 0),
                ':o' => (int) ($fila['omitidos'] ?? 0),
                ':crea' => (string) ($fila['created_at'] ?? date('c')),
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarGrisbi(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT e.fecha_inicio, g.cuenta_grisbi, g.numero, g.asiento_id
             FROM grisbi_vinculos g
             JOIN ejercicios e ON e.id = g.ejercicio_id
             WHERE e.centro_id = :c
             ORDER BY e.fecha_inicio, g.cuenta_grisbi, g.numero'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'ejercicio' => (string) $this->fecha($row['fecha_inicio']),
                'cuenta' => (int) $row['cuenta_grisbi'],
                'numero' => (int) $row['numero'],
                'asiento_ref' => $row['asiento_id'] !== null ? (int) $row['asiento_id'] : null,
            ];
        }

        return $out;
    }

    /** @param array<int, int> $asientos */
    private function restaurarGrisbi(int $centroId, mixed $filas, array $asientos): void
    {
        $this->pdo->prepare(
            'DELETE FROM grisbi_vinculos WHERE ejercicio_id IN (SELECT id FROM ejercicios WHERE centro_id = :c)'
        )->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO grisbi_vinculos (ejercicio_id, cuenta_grisbi, numero, asiento_id)
             VALUES (:e, :c, :n, :a)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ref = isset($fila['asiento_ref']) && $fila['asiento_ref'] !== null ? (int) $fila['asiento_ref'] : 0;
            $st->execute([
                ':e' => $this->exigirEjercicio($centroId, (string) ($fila['ejercicio'] ?? '')),
                ':c' => (int) ($fila['cuenta'] ?? 0),
                ':n' => (int) ($fila['numero'] ?? 0),
                ':a' => $ref > 0 ? ($asientos[$ref] ?? null) : null,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarBanco(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT f.nombre AS fisica, b.banco, b.huella, b.asiento_id, b.fecha, b.importe, b.concepto,
                    b.concepto_asignado, p.iniciales
             FROM banco_centro_import_filas b
             LEFT JOIN cuentas_fisicas f ON f.id = b.cuenta_fisica_id
             LEFT JOIN personas p ON p.id = b.persona_id
             WHERE b.centro_id = :c
             ORDER BY b.fecha, b.id'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'fisica' => $row['fisica'] !== null ? (string) $row['fisica'] : null,
                'banco' => (string) $row['banco'],
                'huella' => (string) $row['huella'],
                'asiento_ref' => $row['asiento_id'] !== null ? (int) $row['asiento_id'] : null,
                'fecha' => (string) $this->fecha($row['fecha']),
                'importe' => (string) $row['importe'],
                'concepto' => (string) $row['concepto'],
                'concepto_asignado' => $row['concepto_asignado'] !== null ? (string) $row['concepto_asignado'] : null,
                'iniciales' => $row['iniciales'] !== null ? strtolower((string) $row['iniciales']) : '',
            ];
        }

        return $out;
    }

    /** @param array<int, int> $asientos */
    private function restaurarBanco(int $centroId, mixed $filas, array $asientos): void
    {
        $this->pdo->prepare('DELETE FROM banco_centro_import_filas WHERE centro_id = :c')->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO banco_centro_import_filas (
                centro_id, cuenta_fisica_id, banco, huella, asiento_id, fecha, importe, concepto,
                concepto_asignado, persona_id
             ) VALUES (:c, :f, :b, :h, :a, :fecha, :imp, :con, :asig, :p)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ref = isset($fila['asiento_ref']) && $fila['asiento_ref'] !== null ? (int) $fila['asiento_ref'] : 0;
            $iniciales = strtolower(trim((string) ($fila['iniciales'] ?? '')));
            $st->execute([
                ':c' => $centroId,
                ':f' => isset($fila['fisica']) && $fila['fisica'] !== ''
                    ? $this->fisicaId($centroId, (string) $fila['fisica']) : null,
                ':b' => (string) ($fila['banco'] ?? ''),
                ':h' => (string) ($fila['huella'] ?? ''),
                ':a' => $ref > 0 ? ($asientos[$ref] ?? null) : null,
                ':fecha' => $this->fecha($fila['fecha'] ?? null),
                ':imp' => (string) ($fila['importe'] ?? '0'),
                ':con' => (string) ($fila['concepto'] ?? ''),
                ':asig' => $fila['concepto_asignado'] ?? null,
                ':p' => $iniciales !== '' ? $this->exigirPersona($centroId, $iniciales) : null,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarArqueos(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT DISTINCT ON (a.id) a.cuenta, a.fecha, a.desglose_json, a.total_dinero, a.total_vales, a.total,
                    a.total_cents, e.fecha_inicio, f.nombre AS fisica
             FROM arqueos a
             LEFT JOIN ejercicios e ON e.id = a.ejercicio_id
             LEFT JOIN cuentas_fisicas f ON f.id = a.cuenta_fisica_id
             WHERE a.ejercicio_id IN (SELECT id FROM ejercicios WHERE centro_id = :ej)
                OR a.cuenta_fisica_id IN (SELECT id FROM cuentas_fisicas WHERE centro_id = :fis)
             ORDER BY a.id'
        );
        $st->execute([':ej' => $centroId, ':fis' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'cuenta' => (string) $row['cuenta'],
                'fecha' => (string) $this->fecha($row['fecha']),
                'desglose' => $this->json($row['desglose_json']),
                'total_dinero' => $row['total_dinero'] !== null ? (string) $row['total_dinero'] : null,
                'total_vales' => $row['total_vales'] !== null ? (string) $row['total_vales'] : null,
                'total' => $row['total'] !== null ? (string) $row['total'] : null,
                'total_cents' => $row['total_cents'] !== null ? (int) $row['total_cents'] : null,
                'ejercicio' => $this->fecha($row['fecha_inicio']),
                'fisica' => $row['fisica'] !== null ? (string) $row['fisica'] : null,
            ];
        }

        return $out;
    }

    private function restaurarArqueos(int $centroId, mixed $filas): void
    {
        $this->pdo->prepare(
            'DELETE FROM arqueos WHERE ejercicio_id IN (SELECT id FROM ejercicios WHERE centro_id = :ej)
                OR cuenta_fisica_id IN (SELECT id FROM cuentas_fisicas WHERE centro_id = :fis)'
        )->execute([':ej' => $centroId, ':fis' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO arqueos (cuenta, fecha, desglose_json, total_dinero, total_vales, total, total_cents, ejercicio_id, cuenta_fisica_id)
             VALUES (:cu, :f, :d, :din, :val, :tot, :cents, :e, :fis)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ejercicio = $this->fecha($fila['ejercicio'] ?? null);
            $st->execute([
                ':cu' => (string) ($fila['cuenta'] ?? ''),
                ':f' => $this->fecha($fila['fecha'] ?? null),
                ':d' => json_encode($fila['desglose'] ?? [], JSON_UNESCAPED_UNICODE),
                ':din' => $fila['total_dinero'] ?? null,
                ':val' => $fila['total_vales'] ?? null,
                ':tot' => $fila['total'] ?? null,
                ':cents' => $fila['total_cents'] ?? null,
                ':e' => $ejercicio !== null ? $this->exigirEjercicio($centroId, $ejercicio) : null,
                ':fis' => isset($fila['fisica']) && $fila['fisica'] !== ''
                    ? $this->fisicaId($centroId, (string) $fila['fisica']) : null,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarArqueosCuadrados(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT fecha, saldos FROM arqueo_cuadrados WHERE centro_id = :c ORDER BY fecha'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'fecha' => (string) $this->fecha($row['fecha']),
                'saldos' => $this->json($row['saldos']),
            ];
        }

        return $out;
    }

    private function restaurarArqueosCuadrados(int $centroId, mixed $filas): void
    {
        $this->pdo->prepare('DELETE FROM arqueo_cuadrados WHERE centro_id = :c')->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO arqueo_cuadrados (centro_id, fecha, saldos) VALUES (:c, :f, :s)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $st->execute([
                ':c' => $centroId,
                ':f' => $this->fecha($fila['fecha'] ?? null),
                ':s' => json_encode($fila['saldos'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarInformes613(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT e.fecha_inicio, i.fecha_cierre, i.cuenta, i.observaciones, i.saldo_cc_personales,
                    i.media_cocina_mes, i.media_cocina_acum, i.dinero_arqueo_caja, i.dinero_arqueo_banco,
                    i.enviado, i.enviado_en
             FROM informes_613_mes i
             JOIN ejercicios e ON e.id = i.ejercicio_id
             WHERE e.centro_id = :c
             ORDER BY i.fecha_cierre, i.cuenta'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'ejercicio' => (string) $this->fecha($row['fecha_inicio']),
                'fecha_cierre' => (string) $this->fecha($row['fecha_cierre']),
                'cuenta' => (string) $row['cuenta'],
                'observaciones' => $row['observaciones'] !== null ? (string) $row['observaciones'] : null,
                'saldo_cc_personales' => $row['saldo_cc_personales'] !== null ? (string) $row['saldo_cc_personales'] : null,
                'media_cocina_mes' => $row['media_cocina_mes'] !== null ? (string) $row['media_cocina_mes'] : null,
                'media_cocina_acum' => $row['media_cocina_acum'] !== null ? (string) $row['media_cocina_acum'] : null,
                'dinero_arqueo_caja' => $row['dinero_arqueo_caja'] !== null ? (string) $row['dinero_arqueo_caja'] : null,
                'dinero_arqueo_banco' => $row['dinero_arqueo_banco'] !== null ? (string) $row['dinero_arqueo_banco'] : null,
                'enviado' => self::booleano($row['enviado']),
                'enviado_en' => $row['enviado_en'] !== null ? (string) $row['enviado_en'] : null,
            ];
        }

        return $out;
    }

    private function restaurarInformes613(int $centroId, mixed $filas): void
    {
        $this->pdo->prepare(
            'DELETE FROM informes_613_mes WHERE ejercicio_id IN (SELECT id FROM ejercicios WHERE centro_id = :c)'
        )->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO informes_613_mes (
                ejercicio_id, fecha_cierre, cuenta, observaciones, saldo_cc_personales, media_cocina_mes,
                media_cocina_acum, dinero_arqueo_caja, dinero_arqueo_banco, enviado, enviado_en
             ) VALUES (:e, :f, :cu, :obs, :saldo, :mes, :acum, :caja, :banco, :env, :cuando)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $st->execute([
                ':e' => $this->exigirEjercicio($centroId, (string) ($fila['ejercicio'] ?? '')),
                ':f' => $this->fecha($fila['fecha_cierre'] ?? null),
                ':cu' => (string) ($fila['cuenta'] ?? 'P'),
                ':obs' => $fila['observaciones'] ?? null,
                ':saldo' => $fila['saldo_cc_personales'] ?? null,
                ':mes' => $fila['media_cocina_mes'] ?? null,
                ':acum' => $fila['media_cocina_acum'] ?? null,
                ':caja' => $fila['dinero_arqueo_caja'] ?? null,
                ':banco' => $fila['dinero_arqueo_banco'] ?? null,
                ':env' => self::booleano($fila['enviado'] ?? false) ? 1 : 0,
                ':cuando' => $fila['enviado_en'] ?? null,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarEntradas(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, iniciales, concepto_codigo, observaciones, cantidad, periodicidad, fecha_ancla, activa
             FROM entradas_periodicas WHERE centro_id = :c ORDER BY id'
        );
        $st->execute([':c' => $centroId]);
        $ej = $this->pdo->prepare(
            'SELECT fecha FROM entradas_periodicas_ejecucion WHERE entrada_periodica_id = :id ORDER BY fecha'
        );
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $ej->execute([':id' => (int) $row['id']]);
            $fechas = [];
            foreach ($ej->fetchAll() as $hecho) {
                $f = $this->fecha($hecho['fecha']);
                if ($f !== null) {
                    $fechas[] = $f;
                }
            }
            $out[] = [
                'iniciales' => strtolower((string) $row['iniciales']),
                'concepto' => (string) $row['concepto_codigo'],
                'observaciones' => $row['observaciones'] !== null ? (string) $row['observaciones'] : null,
                'cantidad' => (string) $row['cantidad'],
                'periodicidad' => (string) $row['periodicidad'],
                'fecha_ancla' => (string) $this->fecha($row['fecha_ancla']),
                'activa' => self::booleano($row['activa']),
                'ejecuciones' => $fechas,
            ];
        }

        return $out;
    }

    private function restaurarEntradas(int $centroId, mixed $filas): void
    {
        $this->pdo->prepare('DELETE FROM entradas_periodicas WHERE centro_id = :c')->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO entradas_periodicas (
                centro_id, iniciales, concepto_codigo, observaciones, cantidad, periodicidad, fecha_ancla, activa
             ) VALUES (:c, :ini, :con, :obs, :cant, :per, :f, :a) RETURNING id'
        );
        $ej = $this->pdo->prepare(
            'INSERT INTO entradas_periodicas_ejecucion (entrada_periodica_id, fecha) VALUES (:id, :f)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ins->execute([
                ':c' => $centroId,
                ':ini' => strtolower(trim((string) ($fila['iniciales'] ?? ''))),
                ':con' => (string) ($fila['concepto'] ?? ''),
                ':obs' => $fila['observaciones'] ?? null,
                ':cant' => (string) ($fila['cantidad'] ?? '0'),
                ':per' => (string) ($fila['periodicidad'] ?? 'mensual'),
                ':f' => $this->fecha($fila['fecha_ancla'] ?? null),
                ':a' => self::booleano($fila['activa'] ?? true) ? 1 : 0,
            ]);
            $id = (int) $ins->fetchColumn();
            foreach ($fila['ejecuciones'] ?? [] as $fecha) {
                $f = $this->fecha($fecha);
                if ($f === null) {
                    continue;
                }
                $ej->execute([':id' => $id, ':f' => $f]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarLabores(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT a.id, a.estado, a.confirmada_at, e.fecha_inicio
             FROM asignaciones_labores a
             JOIN ejercicios e ON e.id = a.ejercicio_id
             WHERE a.centro_id = :c
             ORDER BY a.id'
        );
        $st->execute([':c' => $centroId]);
        $lineas = $this->pdo->prepare(
            'SELECT l.id, p.iniciales, l.codigo_maestro, l.importe_cents, l.pendiente_cents
             FROM asignaciones_labores_lineas l
             JOIN personas p ON p.id = l.persona_id
             WHERE l.asignacion_id = :id
             ORDER BY p.iniciales, l.codigo_maestro'
        );
        $consumos = $this->pdo->prepare(
            'SELECT remesa_id, importe_cents FROM asignacion_labores_consumos WHERE linea_id = :id'
        );
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $lineas->execute([':id' => (int) $row['id']]);
            $movs = [];
            foreach ($lineas->fetchAll() as $lin) {
                $consumos->execute([':id' => (int) $lin['id']]);
                $gastos = [];
                foreach ($consumos->fetchAll() as $con) {
                    $gastos[] = [
                        'remesa_ref' => (int) $con['remesa_id'],
                        'importe_cents' => (int) $con['importe_cents'],
                    ];
                }
                $movs[] = [
                    'iniciales' => strtolower((string) $lin['iniciales']),
                    'codigo' => (string) $lin['codigo_maestro'],
                    'importe_cents' => (int) $lin['importe_cents'],
                    'pendiente_cents' => (int) $lin['pendiente_cents'],
                    'consumos' => $gastos,
                ];
            }
            $out[] = [
                'ref' => (int) $row['id'],
                'ejercicio' => (string) $this->fecha($row['fecha_inicio']),
                'estado' => (string) $row['estado'],
                'confirmada_at' => $row['confirmada_at'] !== null ? (string) $row['confirmada_at'] : null,
                'lineas' => $movs,
            ];
        }

        return $out;
    }

    /**
     * @param array<int, int> $remesas
     * @return array<int, int> ref antigua => id nuevo
     */
    private function restaurarLabores(int $centroId, mixed $filas, array $remesas): array
    {
        $this->pdo->prepare('DELETE FROM asignaciones_labores WHERE centro_id = :c')->execute([':c' => $centroId]);
        $mapa = [];
        if (!is_array($filas)) {
            return $mapa;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO asignaciones_labores (centro_id, ejercicio_id, estado, confirmada_at)
             VALUES (:c, :e, :est, :conf) RETURNING id'
        );
        $lin = $this->pdo->prepare(
            'INSERT INTO asignaciones_labores_lineas (asignacion_id, persona_id, codigo_maestro, importe_cents, pendiente_cents)
             VALUES (:a, :p, :cod, :imp, :pen) RETURNING id'
        );
        $con = $this->pdo->prepare(
            'INSERT INTO asignacion_labores_consumos (linea_id, remesa_id, importe_cents) VALUES (:l, :r, :imp)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ins->execute([
                ':c' => $centroId,
                ':e' => $this->exigirEjercicio($centroId, (string) ($fila['ejercicio'] ?? '')),
                ':est' => (string) ($fila['estado'] ?? 'borrador'),
                ':conf' => $fila['confirmada_at'] ?? null,
            ]);
            $id = (int) $ins->fetchColumn();
            $ref = (int) ($fila['ref'] ?? 0);
            if ($ref > 0) {
                $mapa[$ref] = $id;
            }
            foreach ($fila['lineas'] ?? [] as $mov) {
                if (!is_array($mov)) {
                    continue;
                }
                $lin->execute([
                    ':a' => $id,
                    ':p' => $this->exigirPersona($centroId, strtolower(trim((string) ($mov['iniciales'] ?? '')))),
                    ':cod' => (string) ($mov['codigo'] ?? ''),
                    ':imp' => (int) ($mov['importe_cents'] ?? 0),
                    ':pen' => (int) ($mov['pendiente_cents'] ?? 0),
                ]);
                $lineaId = (int) $lin->fetchColumn();
                foreach ($mov['consumos'] ?? [] as $gasto) {
                    if (!is_array($gasto)) {
                        continue;
                    }
                    $remesaRef = (int) ($gasto['remesa_ref'] ?? 0);
                    $remesaId = $remesas[$remesaRef] ?? null;
                    if ($remesaId === null) {
                        continue;
                    }
                    $con->execute([
                        ':l' => $lineaId,
                        ':r' => $remesaId,
                        ':imp' => (int) ($gasto['importe_cents'] ?? 0),
                    ]);
                }
            }
        }

        return $mapa;
    }

    /** @return array{saldos: list<array<string, mixed>>, movimientos: list<array<string, mixed>>} */
    private function exportarDisponibles(int $centroId): array
    {
        $saldos = $this->pdo->prepare(
            'SELECT p.iniciales, s.saldo_cents
             FROM saldos_disponibles s
             JOIN personas p ON p.id = s.persona_id
             WHERE s.centro_id = :c
             ORDER BY p.iniciales'
        );
        $saldos->execute([':c' => $centroId]);
        $lista = [];
        foreach ($saldos->fetchAll() as $row) {
            $lista[] = [
                'iniciales' => strtolower((string) $row['iniciales']),
                'saldo_cents' => (int) $row['saldo_cents'],
            ];
        }
        $movs = $this->pdo->prepare(
            'SELECT p.iniciales, e.fecha_inicio, m.fecha, m.origen, m.importe_cents, m.remesa_id, m.asignacion_id, m.nota
             FROM saldos_disponibles_mov m
             JOIN personas p ON p.id = m.persona_id
             LEFT JOIN ejercicios e ON e.id = m.ejercicio_id
             WHERE m.centro_id = :c
             ORDER BY m.fecha, m.id'
        );
        $movs->execute([':c' => $centroId]);
        $historia = [];
        foreach ($movs->fetchAll() as $row) {
            $historia[] = [
                'iniciales' => strtolower((string) $row['iniciales']),
                'ejercicio' => $this->fecha($row['fecha_inicio']),
                'fecha' => (string) $this->fecha($row['fecha']),
                'origen' => (string) $row['origen'],
                'importe_cents' => (int) $row['importe_cents'],
                'remesa_ref' => $row['remesa_id'] !== null ? (int) $row['remesa_id'] : null,
                'asignacion_ref' => $row['asignacion_id'] !== null ? (int) $row['asignacion_id'] : null,
                'nota' => $row['nota'] !== null ? (string) $row['nota'] : null,
            ];
        }

        return ['saldos' => $lista, 'movimientos' => $historia];
    }

    /**
     * @param array<int, int> $remesas
     * @param array<int, int> $labores
     */
    private function restaurarDisponibles(int $centroId, mixed $datos, array $remesas, array $labores): void
    {
        $this->pdo->prepare('DELETE FROM saldos_disponibles_mov WHERE centro_id = :c')->execute([':c' => $centroId]);
        $this->pdo->prepare('DELETE FROM saldos_disponibles WHERE centro_id = :c')->execute([':c' => $centroId]);
        if (!is_array($datos)) {
            return;
        }
        $saldo = $this->pdo->prepare(
            'INSERT INTO saldos_disponibles (centro_id, persona_id, saldo_cents) VALUES (:c, :p, :s)'
        );
        foreach ($datos['saldos'] ?? [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $saldo->execute([
                ':c' => $centroId,
                ':p' => $this->exigirPersona($centroId, strtolower(trim((string) ($fila['iniciales'] ?? '')))),
                ':s' => (int) ($fila['saldo_cents'] ?? 0),
            ]);
        }
        $mov = $this->pdo->prepare(
            'INSERT INTO saldos_disponibles_mov (
                centro_id, persona_id, ejercicio_id, fecha, origen, importe_cents, remesa_id, asignacion_id, nota
             ) VALUES (:c, :p, :e, :f, :o, :imp, :r, :a, :n)'
        );
        foreach ($datos['movimientos'] ?? [] as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ejercicio = $this->fecha($fila['ejercicio'] ?? null);
            $remesaRef = isset($fila['remesa_ref']) && $fila['remesa_ref'] !== null ? (int) $fila['remesa_ref'] : 0;
            $asigRef = isset($fila['asignacion_ref']) && $fila['asignacion_ref'] !== null ? (int) $fila['asignacion_ref'] : 0;
            $mov->execute([
                ':c' => $centroId,
                ':p' => $this->exigirPersona($centroId, strtolower(trim((string) ($fila['iniciales'] ?? '')))),
                ':e' => $ejercicio !== null ? $this->exigirEjercicio($centroId, $ejercicio) : null,
                ':f' => $this->fecha($fila['fecha'] ?? null),
                ':o' => (string) ($fila['origen'] ?? 'ajuste'),
                ':imp' => (int) ($fila['importe_cents'] ?? 0),
                ':r' => $remesaRef > 0 ? ($remesas[$remesaRef] ?? null) : null,
                ':a' => $asigRef > 0 ? ($labores[$asigRef] ?? null) : null,
                ':n' => $fila['nota'] ?? null,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarEnvios(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT d.id, d.importe_total_cents, d.estado, d.confirmada_at, e.fecha_inicio
             FROM envios_dl d
             JOIN ejercicios e ON e.id = d.ejercicio_id
             WHERE d.centro_id = :c
             ORDER BY d.id'
        );
        $st->execute([':c' => $centroId]);
        $lineas = $this->pdo->prepare(
            'SELECT p.iniciales, l.importe_cents
             FROM envios_dl_lineas l
             JOIN personas p ON p.id = l.persona_id
             WHERE l.envio_id = :id
             ORDER BY p.iniciales'
        );
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $lineas->execute([':id' => (int) $row['id']]);
            $movs = [];
            foreach ($lineas->fetchAll() as $lin) {
                $movs[] = [
                    'iniciales' => strtolower((string) $lin['iniciales']),
                    'importe_cents' => (int) $lin['importe_cents'],
                ];
            }
            $out[] = [
                'ejercicio' => (string) $this->fecha($row['fecha_inicio']),
                'importe_total_cents' => (int) $row['importe_total_cents'],
                'estado' => (string) $row['estado'],
                'confirmada_at' => $row['confirmada_at'] !== null ? (string) $row['confirmada_at'] : null,
                'lineas' => $movs,
            ];
        }

        return $out;
    }

    private function restaurarEnvios(int $centroId, mixed $filas): void
    {
        $this->pdo->prepare('DELETE FROM envios_dl WHERE centro_id = :c')->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO envios_dl (centro_id, ejercicio_id, importe_total_cents, estado, confirmada_at)
             VALUES (:c, :e, :imp, :est, :conf) RETURNING id'
        );
        $lin = $this->pdo->prepare(
            'INSERT INTO envios_dl_lineas (envio_id, persona_id, importe_cents) VALUES (:env, :p, :imp)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ins->execute([
                ':c' => $centroId,
                ':e' => $this->exigirEjercicio($centroId, (string) ($fila['ejercicio'] ?? '')),
                ':imp' => (int) ($fila['importe_total_cents'] ?? 0),
                ':est' => (string) ($fila['estado'] ?? 'borrador'),
                ':conf' => $fila['confirmada_at'] ?? null,
            ]);
            $id = (int) $ins->fetchColumn();
            foreach ($fila['lineas'] ?? [] as $mov) {
                if (!is_array($mov)) {
                    continue;
                }
                $lin->execute([
                    ':env' => $id,
                    ':p' => $this->exigirPersona($centroId, strtolower(trim((string) ($mov['iniciales'] ?? '')))),
                    ':imp' => (int) ($mov['importe_cents'] ?? 0),
                ]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarCierresMes(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT lower(p.iniciales) AS iniciales, c.anio, c.mes, c.fecha_cierre
             FROM personal_cierre_mes c
             JOIN personas p ON p.id = c.persona_id
             WHERE p.centro_id = :c
             ORDER BY p.iniciales, c.anio, c.mes'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $fecha = $this->fecha($row['fecha_cierre']);
            if ($fecha === null) {
                continue;
            }
            $out[] = [
                'iniciales' => (string) $row['iniciales'],
                'anio' => (int) $row['anio'],
                'mes' => (int) $row['mes'],
                'fecha_cierre' => $fecha,
            ];
        }

        return $out;
    }

    private function restaurarCierresMes(int $centroId, mixed $filas): void
    {
        $this->pdo->prepare(
            'DELETE FROM personal_cierre_mes
             WHERE persona_id IN (SELECT id FROM personas WHERE centro_id = :c)'
        )->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO personal_cierre_mes (persona_id, anio, mes, fecha_cierre)
             VALUES (:p, :a, :m, :f)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $fecha = $this->fecha($fila['fecha_cierre'] ?? null);
            if ($fecha === null) {
                continue;
            }
            $ins->execute([
                ':p' => $this->exigirPersona($centroId, strtolower(trim((string) ($fila['iniciales'] ?? '')))),
                ':a' => (int) ($fila['anio'] ?? 0),
                ':m' => (int) ($fila['mes'] ?? 0),
                ':f' => (new ConverterDate('date', $fecha))->toPg(),
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function exportarBancoPersonal(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT lower(p.iniciales) AS iniciales, f.asiento_id, f.banco, f.huella, f.fecha, f.importe, f.concepto
             FROM banco_import_filas f
             JOIN personas p ON p.id = f.persona_id
             WHERE p.centro_id = :c
             ORDER BY f.id'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $fecha = $this->fecha($row['fecha']);
            if ($fecha === null) {
                continue;
            }
            $out[] = [
                'iniciales' => (string) $row['iniciales'],
                'asiento_ref' => (int) $row['asiento_id'],
                'banco' => (string) $row['banco'],
                'huella' => (string) $row['huella'],
                'fecha' => $fecha,
                'importe' => (string) $row['importe'],
                'concepto' => (string) $row['concepto'],
            ];
        }

        return $out;
    }

    /**
     * @param array<int, int> $asientos
     */
    private function restaurarBancoPersonal(int $centroId, mixed $filas, array $asientos): void
    {
        $this->pdo->prepare(
            'DELETE FROM banco_import_filas
             WHERE persona_id IN (SELECT id FROM personas WHERE centro_id = :c)'
        )->execute([':c' => $centroId]);
        if (!is_array($filas)) {
            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO banco_import_filas (persona_id, banco, huella, asiento_id, fecha, importe, concepto)
             VALUES (:p, :b, :h, :a, :f, :i, :c)'
        );
        foreach ($filas as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $ref = (int) ($fila['asiento_ref'] ?? 0);
            $asientoId = $asientos[$ref] ?? null;
            if ($asientoId === null) {
                continue;
            }
            $ins->execute([
                ':p' => $this->exigirPersona($centroId, strtolower(trim((string) ($fila['iniciales'] ?? '')))),
                ':b' => (string) ($fila['banco'] ?? ''),
                ':h' => (string) ($fila['huella'] ?? ''),
                ':a' => $asientoId,
                ':f' => (new ConverterDate('date', (string) ($fila['fecha'] ?? '')))->toPg(),
                ':i' => (string) ($fila['importe'] ?? ''),
                ':c' => (string) ($fila['concepto'] ?? ''),
            ]);
        }
    }

    private function exigirPersona(int $centroId, string $iniciales): int
    {
        $id = $this->personaId($centroId, $iniciales);
        if ($id === null) {
            throw new InvalidArgumentException(sprintf(
                _('No está la persona «%s» en este centro'),
                $iniciales
            ));
        }

        return $id;
    }

    private function personaId(int $centroId, string $iniciales): ?int
    {
        $st = $this->pdo->prepare(
            'SELECT id FROM personas WHERE centro_id = :c AND lower(iniciales) = lower(:i) ORDER BY id LIMIT 1'
        );
        $st->execute([':c' => $centroId, ':i' => $iniciales]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function exigirEjercicio(int $centroId, string $fecha): int
    {
        $id = $this->ejercicioId($centroId, $fecha);
        if ($id === null) {
            throw new InvalidArgumentException(sprintf(_('No hay ejercicio para la fecha %s'), $fecha));
        }

        return $id;
    }

    private function ejercicioId(int $centroId, string $fecha): ?int
    {
        $st = $this->pdo->prepare(
            'SELECT id FROM ejercicios
             WHERE centro_id = :c AND fecha_inicio <= :f AND fecha_fin >= :f
             ORDER BY fecha_inicio DESC LIMIT 1'
        );
        $st->execute([':c' => $centroId, ':f' => $fecha]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function fisicaId(int $centroId, string $nombre): int
    {
        $st = $this->pdo->prepare(
            'SELECT id FROM cuentas_fisicas WHERE centro_id = :c AND nombre = :n'
        );
        $st->execute([':c' => $centroId, ':n' => $nombre]);
        $id = $st->fetchColumn();
        if ($id === false) {
            throw new InvalidArgumentException(sprintf(_('No está la caja o el banco «%s»'), $nombre));
        }

        return (int) $id;
    }

    private function cuentaId(int $centroId, string $libro, string $codigo, string $iniciales): ?int
    {
        $sql = 'SELECT c.id FROM cuentas c LEFT JOIN personas p ON p.id = c.persona_id
                WHERE c.centro_id = :c AND c.libro = :lib AND c.codigo = :cod AND ';
        $sql .= $iniciales === '' ? 'c.persona_id IS NULL' : 'lower(p.iniciales) = lower(:ini)';
        $sql .= ' LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $params = [':c' => $centroId, ':lib' => $libro, ':cod' => $codigo];
        if ($iniciales !== '') {
            $params[':ini'] = $iniciales;
        }
        $st->execute($params);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function fecha(mixed $valor): ?string
    {
        $f = (new ConverterDate('date', $valor))->fromPg();

        return $f?->format('Y-m-d');
    }

    /** @return array<mixed> */
    private function json(mixed $valor): array
    {
        if (is_string($valor)) {
            $decoded = json_decode($valor, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($valor) ? $valor : [];
    }

    private static function booleano(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_int($valor) || is_float($valor)) {
            return (int) $valor === 1;
        }
        if (is_string($valor)) {
            return in_array(strtolower($valor), ['1', 't', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
