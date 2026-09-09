<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\persistence;

use PDO;
use src\conceptos\domain\services\CatalogoConceptos;
use src\configuracion\domain\entity\ConfiguracionCentro;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\shared\infrastructure\persistence\ConverterDate;

/**
 * Siembra idempotente del ámbito (Fase 2, D2/D3/D10/D11 de
 * docs/dev/plan_ampliaciones.md): centro, ejercicio, cuentas físicas, ámbito de
 * personas y plan de cuentas. Se invoca desde dos sitios (ver docs/dev/ambito.md
 * para el porqué de la duplicación):
 *
 * - `bin/console.php` en la rama `db:migrate`, para que la base real
 *   (`secretario`, que YA tiene una fila de `configuracion`) quede con el ámbito
 *   poblado tras migrar.
 * - `SchemaInstaller::install()`, después de `seedConfig()`, para que una
 *   instalación nueva (`db:install`) también quede poblada.
 *
 * Es completamente idempotente: se puede llamar tantas veces como se quiera
 * (cada `db:migrate`), y no duplica filas ni pisa datos que ya no deberían
 * tocarse (p. ej. no reabre un ejercicio que se cerró a mano).
 *
 * Nunca escribe en `apuntes`, `conceptos`, `presupuesto_lineas` ni `arqueos`:
 * esta fase es aditiva (ver plan_ampliaciones.md, Fase 2).
 */
final class AmbitoSeeder
{
    public static function sembrar(PDO $pdo): void
    {
        $existeConfig = $pdo->query('SELECT id FROM configuracion WHERE id = 1')->fetch();
        if ($existeConfig === false) {
            // Instalación nueva antes de `SchemaInstaller::seedConfig()`: nada que
            // sembrar todavía (no hay centro real que dar de alta).
            return;
        }
        $cfg = (new PdoConfiguracionRepository($pdo))->get();

        $centroId = self::sembrarCentro($pdo, $cfg);
        self::sembrarEjercicio($pdo, $centroId, $cfg);
        self::sembrarAmbitoPersonas($pdo, $centroId);
        self::poblarLibros($pdo, $centroId);
    }

    /**
     * Plan maestro, tesorería, puentes y cuentas personales de un centro ya
     * existente. Idempotente. Lo usa el seeder del centro de `configuracion` y
     * el alta de un segundo centro (Fase 9).
     */
    public static function poblarLibros(PDO $pdo, int $centroId): void
    {
        [$cajaId, $bancoId] = self::sembrarCuentasFisicas($pdo, $centroId);
        self::sembrarPlanMaestro($pdo, $centroId);
        self::sembrarCuentasTesoreria($pdo, $centroId, $cajaId, $bancoId);
        self::sembrarPuenteEntreLibros($pdo, $centroId);
        self::sembrarPuentePeriodificacion($pdo, $centroId);
        self::sembrarResultadoEjerciciosAnteriores($pdo, $centroId);
        self::sembrarCuentasPersonales($pdo, $centroId);
        self::sembrarDeudoresPorVivienda($pdo, $centroId);
    }

    private static function sembrarCentro(PDO $pdo, ConfiguracionCentro $cfg): int
    {
        $codigo = trim($cfg->centro) !== '' ? trim($cfg->centro) : 'CENTRO';
        $st = $pdo->prepare('SELECT id FROM centros WHERE codigo = :c');
        $st->execute([':c' => $codigo]);
        $id = $st->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $ins = $pdo->prepare(
            'INSERT INTO centros (codigo, nombre, tipo_cierre) VALUES (:c, :n, :t) RETURNING id'
        );
        $ins->execute([':c' => $codigo, ':n' => $cfg->centro, ':t' => $cfg->tipoCierre]);

        return (int) $ins->fetchColumn();
    }

    /**
     * Backfill del ejercicio actual (D11): `fecha_fin` es el fin REAL del
     * ejercicio (deducido por `ConfiguracionCentro::periodo()` de anio+modo, NUNCA
     * la fecha de corte); `fecha_corte` es lo que hasta ahora era
     * `configuracion.fecha_cierre`. Confundir estos dos campos es la trampa
     * documentada en `PeriodoEjercicio`.
     *
     * Se mantiene sincronizada con la `configuracion` legada en cada ejecución
     * (fecha_fin, fecha_corte, etiqueta), salvo el `estado`: si alguien cerró el
     * ejercicio a mano, un `db:migrate` posterior no lo reabre.
     */
    private static function sembrarEjercicio(PDO $pdo, int $centroId, ConfiguracionCentro $cfg): void
    {
        $periodo = $cfg->periodo();
        $etiqueta = $cfg->modoEjercicio === 'Curso'
            ? sprintf('%d-%02d', $cfg->anio, ($cfg->anio + 1) % 100)
            : (string) $cfg->anio;
        $fechaInicio = (new ConverterDate('date', $periodo->fechaInicio))->toPg();

        $st = $pdo->prepare('SELECT id FROM ejercicios WHERE centro_id = :c AND fecha_inicio = :fi');
        $st->execute([':c' => $centroId, ':fi' => $fechaInicio]);
        $id = $st->fetchColumn();

        if ($id === false) {
            $ins = $pdo->prepare(
                "INSERT INTO ejercicios (centro_id, etiqueta, fecha_inicio, fecha_fin, fecha_corte, estado)
                 VALUES (:c, :et, :fi, :ff, :fc, 'abierto')"
            );
            $ins->execute([
                ':c' => $centroId,
                ':et' => $etiqueta,
                ':fi' => $fechaInicio,
                ':ff' => (new ConverterDate('date', $periodo->fechaFin))->toPg(),
                ':fc' => (new ConverterDate('date', $periodo->fechaCorte))->toPg(),
            ]);

            return;
        }

        $upd = $pdo->prepare(
            'UPDATE ejercicios SET etiqueta = :et, fecha_fin = :ff, fecha_corte = :fc WHERE id = :id'
        );
        $upd->execute([
            ':et' => $etiqueta,
            ':ff' => (new ConverterDate('date', $periodo->fechaFin))->toPg(),
            ':fc' => (new ConverterDate('date', $periodo->fechaCorte))->toPg(),
            ':id' => $id,
        ]);
    }

    /** @return array{0:int,1:int} [cajaId, bancoId] */
    private static function sembrarCuentasFisicas(PDO $pdo, int $centroId): array
    {
        $caja = self::upsertCuentaFisica($pdo, $centroId, 'caja', 'Caja', 1);
        $banco = self::upsertCuentaFisica($pdo, $centroId, 'banco', 'Banco', 1);

        return [$caja, $banco];
    }

    private static function upsertCuentaFisica(PDO $pdo, int $centroId, string $tipo, string $nombre, int $orden): int
    {
        $st = $pdo->prepare('SELECT id FROM cuentas_fisicas WHERE centro_id = :c AND nombre = :n');
        $st->execute([':c' => $centroId, ':n' => $nombre]);
        $id = $st->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $ins = $pdo->prepare(
            'INSERT INTO cuentas_fisicas (centro_id, tipo, nombre, orden) VALUES (:c, :t, :n, :o) RETURNING id'
        );
        $ins->execute([':c' => $centroId, ':t' => $tipo, ':n' => $nombre, ':o' => $orden]);

        return (int) $ins->fetchColumn();
    }

    /** Backfill de `personas.centro_id`: solo las que todavía no tienen ámbito. */
    private static function sembrarAmbitoPersonas(PDO $pdo, int $centroId): void
    {
        $upd = $pdo->prepare('UPDATE personas SET centro_id = :c WHERE centro_id IS NULL');
        $upd->execute([':c' => $centroId]);
    }

    /**
     * Semilla del plan maestro desde `CatalogoConceptos` (D2). Cada código del
     * catálogo (P y G) se convierte en una cuenta con `codigo_maestro` = su
     * propio código. La `naturaleza` del catálogo decide el `tipo` contable:
     *
     * - ingreso/gasto        -> tipo ingreso/gasto (imputable)
     * - saldo (P/9)          -> tipo personal, NO imputable: es el agregado de las
     *                           cuentas personales de cada persona (ver
     *                           sembrarCuentasPersonales); comprobado contra la
     *                           base real que ningún apunte usa P/9 directamente.
     * - disponible (G/32)    -> tipo patrimonio: es, sin más pasos, la cuenta de
     *                           patrimonio del asiento de apertura que pide el
     *                           punto 2 del encargo (D12 la calculará; hoy sigue
     *                           siendo el importe tecleado a mano).
     * - transferencia (G/41,42) -> tipo puente (D10): las cuentas para los
     *                           traspasos Banco<->Caja. Hoy ningún apunte real usa
     *                           41/42, así que esta parte queda lista para cuando
     *                           se necesite, no ejercitada por datos reales.
     */
    private static function sembrarPlanMaestro(PDO $pdo, int $centroId): void
    {
        foreach (CatalogoConceptos::todos() as $c) {
            [$tipo, $naturaleza, $imputable] = match ($c['naturaleza']) {
                'ingreso' => ['ingreso', 'acreedora', true],
                'gasto' => ['gasto', 'deudora', true],
                'saldo' => ['personal', 'deudora', false],
                'disponible' => ['patrimonio', 'acreedora', true],
                'transferencia' => ['puente', 'deudora', true],
                default => ['gasto', 'deudora', true],
            };
            self::upsertCuenta($pdo, [
                'centro_id' => $centroId,
                'persona_id' => null,
                'cuenta_fisica_id' => null,
                'padre_id' => null,
                'libro' => $c['cuenta'],
                'codigo' => $c['codigo'],
                'nombre' => $c['nombre'],
                'descripcion' => $c['descripcion'],
                'tipo' => $tipo,
                'naturaleza' => $naturaleza,
                'codigo_maestro' => $c['codigo'],
                'imputable' => $imputable,
                'orden' => $c['orden'],
            ]);
        }
    }

    /**
     * Cuentas de mayor por física x libro (D10): `CAJA.1/P`, `CAJA.1/G`,
     * `BANCO.1/P`, `BANCO.1/G`. El código maestro sintético (`CAJA`, `BANCO`) no
     * viene de `CatalogoConceptos` porque la tesorería no es un concepto del 613:
     * es la contrapartida de todos ellos.
     */
    private static function sembrarCuentasTesoreria(PDO $pdo, int $centroId, int $cajaId, int $bancoId): void
    {
        $fisicas = [
            ['id' => $cajaId, 'maestro' => 'CAJA', 'nombre' => 'Caja', 'orden' => 1],
            ['id' => $bancoId, 'maestro' => 'BANCO', 'nombre' => 'Banco', 'orden' => 1],
        ];
        foreach ($fisicas as $f) {
            foreach (['P', 'G'] as $libro) {
                $codigo = sprintf('%s.%d/%s', $f['maestro'], $f['orden'], $libro);
                self::upsertCuenta($pdo, [
                    'centro_id' => $centroId,
                    'persona_id' => null,
                    'cuenta_fisica_id' => $f['id'],
                    'padre_id' => null,
                    'libro' => $libro,
                    'codigo' => $codigo,
                    'nombre' => sprintf('%s · %s', $f['nombre'], $libro),
                    'descripcion' => sprintf('Cuenta de mayor de %s en el libro %s (D10)', $f['nombre'], $libro),
                    'tipo' => 'tesoreria',
                    'naturaleza' => 'deudora',
                    'codigo_maestro' => $f['maestro'],
                    'imputable' => true,
                    'orden' => $f['orden'],
                ]);
            }
        }
    }

    /**
     * Cuenta puente para préstamos entre libros P↔G sobre la misma tesorería física (D10).
     * Distinta de G/41 y G/42 (traspaso caja↔banco del Excel).
     */
    private static function sembrarPuenteEntreLibros(PDO $pdo, int $centroId): void
    {
        foreach (['P', 'G'] as $libro) {
            self::upsertCuenta($pdo, [
                'centro_id' => $centroId,
                'persona_id' => null,
                'cuenta_fisica_id' => null,
                'padre_id' => null,
                'libro' => $libro,
                'codigo' => 'PUENTE.LIBROS',
                'nombre' => 'Puente entre libros · ' . $libro,
                'descripcion' => 'Contrapartida de préstamos entre P y G sobre la misma cuenta física (D10)',
                'tipo' => 'puente',
                'naturaleza' => 'deudora',
                'codigo_maestro' => 'PUENTE',
                'imputable' => true,
                'orden' => 0,
            ]);
        }
    }

    /**
     * Contrapartida de gasto/ingreso imputado a un período y tesorería en otro (D13).
     * Distinta de PUENTE.LIBROS (préstamo P↔G) y de G/41-42 (traspaso caja↔banco).
     */
    private static function sembrarPuentePeriodificacion(PDO $pdo, int $centroId): void
    {
        foreach (['P', 'G'] as $libro) {
            self::upsertCuenta($pdo, [
                'centro_id' => $centroId,
                'persona_id' => null,
                'cuenta_fisica_id' => null,
                'padre_id' => null,
                'libro' => $libro,
                'codigo' => 'PUENTE.PERIODIFICACION',
                'nombre' => 'Periodificación · ' . $libro,
                'descripcion' => 'Contrapartida de imputación a período distinto de la tesorería (D13)',
                'tipo' => 'puente',
                'naturaleza' => 'deudora',
                'codigo_maestro' => 'PERIODIFICACION',
                'imputable' => true,
                'orden' => 0,
            ]);
        }
    }

    /**
     * Contrapartida de patrimonio del asiento de apertura en P (D12). No está en
     * `CatalogoConceptos`: se siembra como `PUENTE.LIBROS` o `DEUDORES.VIV`.
     */
    private static function sembrarResultadoEjerciciosAnteriores(PDO $pdo, int $centroId): void
    {
        self::upsertCuenta($pdo, [
            'centro_id' => $centroId,
            'persona_id' => null,
            'cuenta_fisica_id' => null,
            'padre_id' => null,
            'libro' => 'P',
            'codigo' => 'RESULTADO.ANT',
            'nombre' => 'Resultado de ejercicios anteriores',
            'descripcion' => 'Contrapartida del asiento de apertura en el libro P (D12)',
            'tipo' => 'patrimonio',
            'naturaleza' => 'acreedora',
            'codigo_maestro' => 'RESULTADO',
            'imputable' => true,
            'orden' => 998,
        ]);
    }

    /**
     * Una cuenta personal (libro P) por persona del centro, con `codigo_maestro`
     * = '9' (rueda dentro del agregado "Saldo en las c/c personales" del plan
     * maestro). Es la contrapartida individual de los movimientos de origen A.
     */
    private static function sembrarCuentasPersonales(PDO $pdo, int $centroId): void
    {
        $st = $pdo->prepare('SELECT id, nombre, apellidos, iniciales FROM personas WHERE centro_id = :c');
        $st->execute([':c' => $centroId]);
        foreach ($st->fetchAll() as $p) {
            $iniciales = strtoupper((string) $p['iniciales']);
            $nombreCompleto = trim($p['nombre'] . ' ' . $p['apellidos']);
            self::upsertCuenta($pdo, [
                'centro_id' => $centroId,
                'persona_id' => (int) $p['id'],
                'cuenta_fisica_id' => null,
                'padre_id' => null,
                'libro' => 'P',
                'codigo' => 'CC.' . $iniciales,
                'nombre' => 'Cuenta personal de ' . $nombreCompleto,
                'descripcion' => 'Cuenta personal (c/c) de ' . $nombreCompleto . ' dentro del libro P',
                'tipo' => 'personal',
                'naturaleza' => 'deudora',
                'codigo_maestro' => '9',
                'imputable' => true,
                'orden' => 0,
            ]);
        }
    }

    /**
     * Contrapartida colectiva en G de los movimientos de origen A (persona <->
     * centro) que no pasan por caja ni banco: en el libro P cada persona tiene su
     * cuenta personal (CC.*), pero G no distingue personas, así que necesita UNA
     * cuenta que agregue "lo que deben/se debe a los residentes" en conjunto.
     * `codigo_maestro` es sintético (`DEUDORES-VIV`) por la misma razón que
     * CAJA/BANCO: no es un concepto del 613, es la contrapartida agregada de
     * muchos.
     */
    private static function sembrarDeudoresPorVivienda(PDO $pdo, int $centroId): void
    {
        self::upsertCuenta($pdo, [
            'centro_id' => $centroId,
            'persona_id' => null,
            'cuenta_fisica_id' => null,
            'padre_id' => null,
            'libro' => 'G',
            'codigo' => 'DEUDORES.VIV',
            'nombre' => 'Deudores por vivienda (personas)',
            'descripcion' => 'Contrapartida colectiva en G de los movimientos de origen A que no pasan por caja/banco',
            'tipo' => 'personal',
            'naturaleza' => 'deudora',
            'codigo_maestro' => 'DEUDORES-VIV',
            'imputable' => true,
            'orden' => 999,
        ]);
    }

    /**
     * Upsert manual (no `ON CONFLICT`) porque la UNIQUE (centro_id, persona_id,
     * libro, codigo) no sirve como objetivo de conflicto cuando `persona_id` es
     * NULL (Postgres trata cada NULL como distinto). Se resuelve buscando primero
     * con `persona_id IS NULL` explícito.
     *
     * @param array{centro_id:int,persona_id:?int,cuenta_fisica_id:?int,padre_id:?int,
     *     libro:string,codigo:string,nombre:string,descripcion:string,tipo:string,
     *     naturaleza:string,codigo_maestro:string,imputable:bool,orden:int} $c
     */
    /**
     * @param array{centro_id:int,persona_id:?int,cuenta_fisica_id:?int,padre_id:?int,
     *     libro:string,codigo:string,nombre:string,descripcion:string,tipo:string,
     *     naturaleza:string,codigo_maestro:string,imputable:bool,orden:int} $c
     */
    public static function upsertCuenta(PDO $pdo, array $c): void
    {
        $sql = 'SELECT id FROM cuentas WHERE centro_id = :centro AND libro = :libro AND codigo = :codigo AND '
            . ($c['persona_id'] === null ? 'persona_id IS NULL' : 'persona_id = :persona');
        $st = $pdo->prepare($sql);
        $params = [':centro' => $c['centro_id'], ':libro' => $c['libro'], ':codigo' => $c['codigo']];
        if ($c['persona_id'] !== null) {
            $params[':persona'] = $c['persona_id'];
        }
        $st->execute($params);
        $id = $st->fetchColumn();

        if ($id === false) {
            $ins = $pdo->prepare(
                'INSERT INTO cuentas (centro_id, persona_id, cuenta_fisica_id, padre_id, libro, codigo,
                    nombre, descripcion, tipo, naturaleza, codigo_maestro, imputable, orden)
                 VALUES (:centro, :persona, :fisica, :padre, :libro, :codigo,
                    :nombre, :descripcion, :tipo, :naturaleza, :maestro, :imputable, :orden)'
            );
            $ins->execute([
                ':centro' => $c['centro_id'],
                ':persona' => $c['persona_id'],
                ':fisica' => $c['cuenta_fisica_id'],
                ':padre' => $c['padre_id'],
                ':libro' => $c['libro'],
                ':codigo' => $c['codigo'],
                ':nombre' => $c['nombre'],
                ':descripcion' => $c['descripcion'],
                ':tipo' => $c['tipo'],
                ':naturaleza' => $c['naturaleza'],
                ':maestro' => $c['codigo_maestro'],
                // Se envía como 0/1, no como bool: PDOStatement::execute() con un array
                // castea `false` a '' (cadena vacía), que Postgres rechaza para boolean
                // ('invalid input syntax for type boolean: ""'); en cambio '0'/'1' son
                // literales booleanos válidos.
                ':imputable' => (int) $c['imputable'],
                ':orden' => $c['orden'],
            ]);

            return;
        }

        $upd = $pdo->prepare(
            'UPDATE cuentas SET cuenta_fisica_id = :fisica, padre_id = :padre, nombre = :nombre,
                descripcion = :descripcion, tipo = :tipo, naturaleza = :naturaleza,
                codigo_maestro = :maestro, imputable = :imputable, orden = :orden
             WHERE id = :id'
        );
        $upd->execute([
            ':fisica' => $c['cuenta_fisica_id'],
            ':padre' => $c['padre_id'],
            ':nombre' => $c['nombre'],
            ':descripcion' => $c['descripcion'],
            ':tipo' => $c['tipo'],
            ':naturaleza' => $c['naturaleza'],
            ':maestro' => $c['codigo_maestro'],
            ':imputable' => (int) $c['imputable'],
            ':orden' => $c['orden'],
            ':id' => $id,
        ]);
    }
}
