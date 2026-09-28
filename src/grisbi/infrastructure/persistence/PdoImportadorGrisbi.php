<?php

declare(strict_types=1);

namespace src\grisbi\infrastructure\persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\arqueo\infrastructure\persistence\PdoArqueoCuadrado;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\grisbi\domain\CategoriaGrisbi;
use src\grisbi\domain\CuentaGrisbi;
use src\grisbi\domain\LibroGrisbi;
use src\grisbi\domain\ListadoGrisbi;
use src\grisbi\domain\MovimientoGrisbi;
use src\grisbi\domain\SubcategoriaGrisbi;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\persistence\ConverterDate;

/**
 * Pasa un libro Grisbi a asientos del libro G. Reimportar el mismo número no duplica.
 */
final class PdoImportadorGrisbi
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AsientoRepository $asientos,
    ) {
    }

    /**
     * @return array{altas:int, omitidos:int, listados:int, avisos:list<string>}
     */
    public function ejecutar(int $centroId, LibroGrisbi $libro): array
    {
        $this->exigirPlanClub($centroId);
        $this->asegurarEjercicios($centroId, $libro);
        $altas = 0;
        $omitidos = 0;
        $avisos = [];
        $tesoreria = [];
        foreach ($libro->cuentas as $cuenta) {
            $tesoreria[$cuenta->numero] = $this->tesoreriaId($centroId, $cuenta);
        }
        $codigos = $this->codigosCategoria($libro);
        $nombresTercero = [];
        foreach ($libro->terceros as $tercero) {
            $nombresTercero[$tercero->numero] = $tercero->nombre;
        }
        $vistos = [];
        foreach ($libro->movimientos as $mov) {
            $clave = $mov->cuenta . ':' . $mov->numero;
            if (isset($vistos[$clave])) {
                continue;
            }
            if ($this->yaImportado($centroId, $mov)) {
                $vistos[$clave] = true;
                $omitidos++;
                continue;
            }
            $par = $this->parejaTraspaso($libro, $mov);
            if ($par !== null) {
                $vistos[$clave] = true;
                $vistos[$par->cuenta . ':' . $par->numero] = true;
                if ($this->yaImportado($centroId, $par)) {
                    $omitidos++;
                    continue;
                }
                $asiento = $this->asientoTraspaso($mov, $par, $tesoreria);
                if ($asiento === null) {
                    $omitidos++;
                    $avisos[] = sprintf('Traspaso %d/%d sin ejercicio', $mov->cuenta, $mov->numero);
                    continue;
                }
                $guardado = $this->asientos->guardar($asiento, true);
                $this->vincular($mov, (int) $guardado->id);
                $this->vincular($par, (int) $guardado->id);
                $altas++;
                continue;
            }
            $vistos[$clave] = true;
            $asiento = $this->asientoNormal($centroId, $mov, $tesoreria, $codigos, $nombresTercero, $libro);
            if ($asiento === null) {
                $omitidos++;
                $avisos[] = sprintf('Movimiento %d/%d sin ejercicio o sin importe', $mov->cuenta, $mov->numero);
                continue;
            }
            $guardado = $this->asientos->guardar($asiento, true);
            $this->vincular($mov, (int) $guardado->id);
            $altas++;
        }
        $listados = 0;
        foreach ($libro->listados as $listado) {
            $this->guardarListado($centroId, $listado, $codigos, $nombresTercero);
            $listados++;
        }

        return [
            'altas' => $altas,
            'omitidos' => $omitidos,
            'listados' => $listados,
            'avisos' => array_slice($avisos, 0, 20),
        ];
    }

    /**
     * @return list<array{fecha:string,codigo:string,nombre:string,glosa:string,importe:string}>
     */
    public function listarMovimientos(int $centroId, string $desde, string $hasta, int $ejercicioId = 0): array
    {
        $this->exigirPlanClub($centroId);
        if ($desde === '' && $hasta === '') {
            if ($ejercicioId <= 0) {
                $ejercicioId = $this->ejercicioAbierto($centroId);
            }
        } else {
            $ejercicioId = 0;
        }
        $sql = "SELECT a.id AS asiento_id, a.fecha::text AS fecha, COALESCE(a.glosa, '') AS glosa,
                COALESCE(contra.cuenta_id, 0) AS cuenta_id,
                COALESCE(contra.codigo, tes.codigo, '') AS codigo,
                COALESCE(contra.nombre, tes.nombre, '') AS nombre,
                COALESCE(contra.tipo, '') AS tipo,
                COALESCE(tes.nombre, '') AS tesoreria,
                CASE WHEN contra.tipo = 'gasto' THEN contra.haber - contra.debe
                     WHEN contra.tipo = 'ingreso' THEN contra.haber - contra.debe
                     WHEN contra.codigo IS NULL THEN ABS(tes.debe - tes.haber)
                     ELSE ABS(contra.haber - contra.debe) END AS cents
             FROM asientos a
             JOIN ejercicios e ON e.id = a.ejercicio_id
             LEFT JOIN LATERAL (
                SELECT m.cuenta_id, c.codigo, c.nombre, c.tipo, m.debe, m.haber
                FROM movimientos m JOIN cuentas c ON c.id = m.cuenta_id
                WHERE m.asiento_id = a.id AND c.tipo <> 'tesoreria'
                ORDER BY m.orden LIMIT 1
             ) contra ON TRUE
             LEFT JOIN LATERAL (
                SELECT c.codigo, c.nombre, m.debe, m.haber
                FROM movimientos m JOIN cuentas c ON c.id = m.cuenta_id
                WHERE m.asiento_id = a.id AND c.tipo = 'tesoreria'
                ORDER BY m.orden LIMIT 1
             ) tes ON TRUE
             WHERE e.centro_id = :c AND a.anulado_at IS NULL AND a.libro = 'G'";
        $params = [':c' => $centroId];
        if ($ejercicioId > 0) {
            $sql .= ' AND a.ejercicio_id = :ej';
            $params[':ej'] = $ejercicioId;
        }
        if ($desde !== '') {
            $sql .= ' AND a.fecha >= :desde';
            $params[':desde'] = $desde;
        }
        if ($hasta !== '') {
            $sql .= ' AND a.fecha <= :hasta';
            $params[':hasta'] = $hasta;
        }
        $sql .= ' ORDER BY a.fecha, a.numero';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $cents = (int) $row['cents'];
            $out[] = [
                'id' => (int) $row['asiento_id'],
                'fecha' => (string) $row['fecha'],
                'cuenta_id' => (int) $row['cuenta_id'],
                'codigo' => (string) $row['codigo'],
                'nombre' => (string) $row['nombre'],
                'tipo' => (string) $row['tipo'],
                'tesoreria' => (string) $row['tesoreria'],
                'glosa' => (string) $row['glosa'],
                'cents' => $cents,
                'importe' => number_format($cents / 100, 2, ',', '.'),
            ];
        }

        return $out;
    }

    /** @return list<array{id:int,codigo:string,nombre:string,tipo:string}> */
    public function cuentasEditables(int $centroId): array
    {
        $this->exigirPlanClub($centroId);
        $st = $this->pdo->prepare(
            "SELECT id, codigo, nombre, tipo, imputable FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND persona_id IS NULL AND activo = TRUE
               AND tipo IN ('ingreso', 'gasto')
               AND codigo ~ '^(60|61|70|80|90)(\\.[0-9]+)?$'
             ORDER BY codigo"
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'codigo' => (string) $row['codigo'],
                'nombre' => (string) $row['nombre'],
                'tipo' => (string) $row['tipo'],
                'grupo' => !$row['imputable'],
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $datos @return list<string> */
    public function editarMovimiento(int $centroId, int $asientoId, array $datos): array
    {
        $this->exigirPlanClub($centroId);
        $asiento = $this->asientoDelCentro($centroId, $asientoId);
        $fechaAnterior = (string) $asiento['fecha'];
        $fecha = trim((string) ($datos['fecha'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            throw new InvalidArgumentException(_('Fecha no válida'));
        }
        $ejercicioId = $this->ejercicioDe($fecha, $centroId);
        if ($ejercicioId === null) {
            throw new InvalidArgumentException(_('La fecha no cae en ningún ejercicio'));
        }
        $cents = $this->centimos((string) ($datos['importe'] ?? ''));
        if ($cents <= 0) {
            throw new InvalidArgumentException(_('El importe tiene que ser mayor que cero'));
        }
        $glosa = trim((string) ($datos['glosa'] ?? ''));
        $lineas = $this->lineas($asientoId);
        $categoria = null;
        $tesorerias = [];
        foreach ($lineas as $linea) {
            if ($linea['tipo'] === 'tesoreria') {
                $tesorerias[] = $linea;
            } else {
                $categoria = $linea;
            }
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'UPDATE asientos SET fecha = :f, fecha_operacion = :f, ejercicio_id = :ej, glosa = :g, updated_at = now()
                 WHERE id = :id'
            )->execute([':f' => $fecha, ':ej' => $ejercicioId, ':g' => $glosa !== '' ? $glosa : null, ':id' => $asientoId]);
            if ($categoria !== null) {
                $cuentaId = (int) ($datos['cuenta_id'] ?? $categoria['cuenta_id']);
                $tipo = $this->tipoCuenta($centroId, $cuentaId);
                $debeCat = $tipo === 'gasto' ? $cents : 0;
                $haberCat = $tipo === 'gasto' ? 0 : $cents;
                $this->ponerImporte($categoria['id'], $cuentaId, $debeCat, $haberCat);
                if ($tesorerias !== []) {
                    $this->ponerImporte($tesorerias[0]['id'], $tesorerias[0]['cuenta_id'], $haberCat, $debeCat);
                }
            } elseif (count($tesorerias) >= 2) {
                foreach ($tesorerias as $linea) {
                    $debe = (int) $linea['debe'] > 0 ? $cents : 0;
                    $haber = (int) $linea['haber'] > 0 ? $cents : 0;
                    $this->ponerImporte($linea['id'], $linea['cuenta_id'], $debe, $haber);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        $desde = $fechaAnterior < $fecha ? $fechaAnterior : $fecha;

        return (new PdoArqueoCuadrado($this->pdo, $this->asientos))->avisos($centroId, $desde);
    }

    /** @return list<string> */
    public function borrarMovimiento(int $centroId, int $asientoId): array
    {
        $this->exigirPlanClub($centroId);
        $asiento = $this->asientoDelCentro($centroId, $asientoId);
        $this->pdo->prepare('UPDATE asientos SET asiento_par_id = NULL WHERE id = :id OR asiento_par_id = :id')->execute([':id' => $asientoId]);
        $this->pdo->prepare('DELETE FROM asientos WHERE id = :id')->execute([':id' => $asientoId]);

        return (new PdoArqueoCuadrado($this->pdo, $this->asientos))->avisos($centroId, (string) $asiento['fecha']);
    }

    /** @return array{id:int,fecha:string} */
    private function asientoDelCentro(int $centroId, int $asientoId): array
    {
        $st = $this->pdo->prepare(
            "SELECT a.id, a.fecha::text AS fecha FROM asientos a
             JOIN ejercicios e ON e.id = a.ejercicio_id
             WHERE a.id = :id AND e.centro_id = :c AND a.libro = 'G' AND a.anulado_at IS NULL"
        );
        $st->execute([':id' => $asientoId, ':c' => $centroId]);
        $row = $st->fetch();
        if (!is_array($row)) {
            throw new InvalidArgumentException(_('Apunte no encontrado'));
        }

        return ['id' => (int) $row['id'], 'fecha' => (string) $row['fecha']];
    }

    /** @return list<array{id:int,cuenta_id:int,tipo:string,debe:int,haber:int}> */
    private function lineas(int $asientoId): array
    {
        $st = $this->pdo->prepare(
            'SELECT m.id, m.cuenta_id, c.tipo, m.debe, m.haber
             FROM movimientos m JOIN cuentas c ON c.id = m.cuenta_id
             WHERE m.asiento_id = :id ORDER BY m.orden'
        );
        $st->execute([':id' => $asientoId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'cuenta_id' => (int) $row['cuenta_id'],
                'tipo' => (string) $row['tipo'],
                'debe' => (int) $row['debe'],
                'haber' => (int) $row['haber'],
            ];
        }

        return $out;
    }

    private function tipoCuenta(int $centroId, int $cuentaId): string
    {
        $st = $this->pdo->prepare(
            "SELECT tipo FROM cuentas
             WHERE id = :id AND centro_id = :c AND libro = 'G' AND tipo IN ('ingreso', 'gasto') AND activo = TRUE"
        );
        $st->execute([':id' => $cuentaId, ':c' => $centroId]);
        $tipo = $st->fetchColumn();
        if (!is_string($tipo)) {
            throw new InvalidArgumentException(_('La cuenta no es válida'));
        }

        return $tipo;
    }

    private function ponerImporte(int $movimientoId, int $cuentaId, int $debe, int $haber): void
    {
        $this->pdo->prepare(
            'UPDATE movimientos SET cuenta_id = :c, debe = :d, haber = :h WHERE id = :id'
        )->execute([':c' => $cuentaId, ':d' => $debe, ':h' => $haber, ':id' => $movimientoId]);
    }

    private function centimos(string $texto): int
    {
        $texto = trim(str_replace(' ', '', $texto));
        if ($texto === '') {
            return 0;
        }
        if (str_contains($texto, ',')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        }
        if (!is_numeric($texto)) {
            throw new InvalidArgumentException(_('El importe no es válido'));
        }

        return (int) round((float) $texto * 100);
    }

    /** Años del fichero que no pisan un ejercicio ya existente se abren cerrados. */
    private function asegurarEjercicios(int $centroId, LibroGrisbi $libro): void
    {
        $anios = [];
        foreach ($libro->movimientos as $mov) {
            $anio = substr($mov->fecha, 0, 4);
            if (preg_match('/^\d{4}$/', $anio) === 1) {
                $anios[$anio] = true;
            }
        }
        $solapa = $this->pdo->prepare(
            'SELECT 1 FROM ejercicios
             WHERE centro_id = :c AND fecha_inicio <= :fin AND fecha_fin >= :ini LIMIT 1'
        );
        $ins = $this->pdo->prepare(
            "INSERT INTO ejercicios (centro_id, etiqueta, fecha_inicio, fecha_fin, fecha_corte, estado)
             VALUES (:c, :et, :fi, :ff, :fc, 'cerrado')"
        );
        foreach (array_keys($anios) as $anio) {
            $ini = $anio . '-01-01';
            $fin = $anio . '-12-31';
            $solapa->execute([':c' => $centroId, ':ini' => $ini, ':fin' => $fin]);
            if ($solapa->fetchColumn() !== false) {
                continue;
            }
            $ins->execute([
                ':c' => $centroId,
                ':et' => $anio,
                ':fi' => (new ConverterDate('date', $ini))->toPg(),
                ':ff' => (new ConverterDate('date', $fin))->toPg(),
                ':fc' => (new ConverterDate('date', $ini))->toPg(),
            ]);
        }
    }

    private function exigirPlanClub(int $centroId): void
    {
        $st = $this->pdo->prepare(
            "SELECT COALESCE(p.codigo, '') FROM centros c
             LEFT JOIN planes_contables p ON p.id = c.plan_contable_id
             WHERE c.id = :id"
        );
        $st->execute([':id' => $centroId]);
        $codigo = $st->fetchColumn();
        if (!is_string($codigo) || !CatalogoPlanesContables::esClub($codigo)) {
            throw new InvalidArgumentException(_('La importación Grisbi solo está disponible en un centro con plan Club'));
        }
    }

    private function tesoreriaId(int $centroId, CuentaGrisbi $cuenta): int
    {
        $st = $this->pdo->prepare(
            'SELECT id FROM cuentas_fisicas WHERE centro_id = :c AND tipo = :t AND lower(nombre) = lower(:n) LIMIT 1'
        );
        $st->execute([':c' => $centroId, ':t' => $cuenta->tipo, ':n' => $cuenta->nombre]);
        $fisicaId = $st->fetchColumn();
        if ($fisicaId === false) {
            $ordenSt = $this->pdo->prepare(
                'SELECT COALESCE(MAX(orden), 0) + 1 FROM cuentas_fisicas WHERE centro_id = :c AND tipo = :t'
            );
            $ordenSt->execute([':c' => $centroId, ':t' => $cuenta->tipo]);
            $orden = (int) $ordenSt->fetchColumn();
            $ins = $this->pdo->prepare(
                'INSERT INTO cuentas_fisicas (centro_id, tipo, nombre, orden) VALUES (:c, :t, :n, :o) RETURNING id'
            );
            $ins->execute([':c' => $centroId, ':t' => $cuenta->tipo, ':n' => $cuenta->nombre, ':o' => $orden]);
            $fisicaId = (int) $ins->fetchColumn();
        } else {
            $fisicaId = (int) $fisicaId;
        }
        $mayor = $this->pdo->prepare(
            "SELECT id FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND cuenta_fisica_id = :f AND tipo = 'tesoreria' AND activo = TRUE
             LIMIT 1"
        );
        $mayor->execute([':c' => $centroId, ':f' => $fisicaId]);
        $id = $mayor->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $maestro = $cuenta->tipo === 'caja' ? 'CAJA' : 'BANCO';
        $codigo = sprintf('%s.%d/G', $maestro, $cuenta->numero + 1);
        AmbitoSeeder::upsertCuenta($this->pdo, [
            'centro_id' => $centroId,
            'persona_id' => null,
            'cuenta_fisica_id' => $fisicaId,
            'padre_id' => null,
            'libro' => 'G',
            'codigo' => $codigo,
            'nombre' => $cuenta->nombre,
            'descripcion' => $cuenta->nombre,
            'tipo' => 'tesoreria',
            'naturaleza' => 'deudora',
            'codigo_maestro' => $maestro,
            'imputable' => true,
            'orden' => $cuenta->numero + 1,
        ]);
        $mayor->execute([':c' => $centroId, ':f' => $fisicaId]);

        return (int) $mayor->fetchColumn();
    }

    /**
     * @return array<string, string> clave "cat" o "cat.sub" => codigo de cuenta
     */
    private function codigosCategoria(LibroGrisbi $libro): array
    {
        $codigos = [];
        foreach ($libro->categorias as $categoria) {
            if ($categoria->naturaleza === 'traspaso' || $categoria->naturaleza === 'apertura') {
                continue;
            }
            $grupo = self::grupoContable($categoria->nombre);
            if ($grupo === null) {
                continue;
            }
            $subs = array_values(array_filter(
                $libro->subcategorias,
                static fn (SubcategoriaGrisbi $s): bool => $s->categoria === $categoria->numero && $s->nombre !== ''
            ));
            if ($subs === []) {
                $codigos[(string) $categoria->numero] = $grupo;
            }
            foreach ($subs as $sub) {
                $codigos[$categoria->numero . '.' . $sub->numero] = $grupo . '.' . $sub->numero;
            }
        }

        return $codigos;
    }

    private function parejaTraspaso(LibroGrisbi $libro, MovimientoGrisbi $mov): ?MovimientoGrisbi
    {
        if ($mov->traspasoNb === 0) {
            return null;
        }
        foreach ($libro->movimientos as $otro) {
            if ($otro->numero === $mov->traspasoNb && $otro->traspasoNb === $mov->numero && $otro->cuenta !== $mov->cuenta) {
                return $otro;
            }
        }

        return null;
    }

    private function yaImportado(int $centroId, MovimientoGrisbi $mov): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM grisbi_vinculos v
             JOIN ejercicios e ON e.id = v.ejercicio_id
             WHERE e.centro_id = :centro AND v.cuenta_grisbi = :c AND v.numero = :n
               AND v.asiento_id IS NOT NULL
               AND e.fecha_inicio <= :f AND e.fecha_fin >= :f
             LIMIT 1'
        );
        $st->execute([':centro' => $centroId, ':c' => $mov->cuenta, ':n' => $mov->numero, ':f' => $mov->fecha]);

        return $st->fetchColumn() !== false;
    }

    /** @param array<int, int> $tesoreria */
    private function asientoTraspaso(MovimientoGrisbi $mov, MovimientoGrisbi $par, array $tesoreria): ?Asiento
    {
        $ejercicioId = $this->ejercicioDe($mov->fecha, $this->centroDeTesoreria($tesoreria[$mov->cuenta] ?? 0));
        if ($ejercicioId === null || $mov->importeCents === 0) {
            return null;
        }
        $entra = $mov->importeCents > 0 ? $mov : $par;
        $sale = $entra === $mov ? $par : $mov;
        $cents = abs($entra->importeCents);
        $debeCuenta = $tesoreria[$entra->cuenta] ?? null;
        $haberCuenta = $tesoreria[$sale->cuenta] ?? null;
        if ($debeCuenta === null || $haberCuenta === null) {
            return null;
        }

        return new Asiento(
            null,
            $ejercicioId,
            'G',
            null,
            new DateTimeImmutable($entra->fecha),
            _('Traspaso'),
            'traspaso',
            'import',
            null,
            [
                new Movimiento(null, 1, $debeCuenta, null, $cents, 0),
                new Movimiento(null, 2, $haberCuenta, null, 0, $cents),
            ],
        );
    }

    /**
     * @param array<int, int> $tesoreria
     * @param array<string, string> $codigos
     * @param array<int, string> $nombresTercero
     */
    private function asientoNormal(
        int $centroId,
        MovimientoGrisbi $mov,
        array $tesoreria,
        array $codigos,
        array $nombresTercero,
        LibroGrisbi $libro,
    ): ?Asiento {
        if ($mov->importeCents === 0 || !isset($tesoreria[$mov->cuenta])) {
            return null;
        }
        $ejercicioId = $this->ejercicioDe($mov->fecha, $centroId);
        if ($ejercicioId === null) {
            return null;
        }
        $categoria = $this->categoriaDe($libro, $mov->categoria);
        $apertura = $categoria !== null && $categoria->naturaleza === 'apertura';
        $clave = $mov->subcategoria > 0 ? $mov->categoria . '.' . $mov->subcategoria : (string) $mov->categoria;
        $codigo = $apertura ? 'APERTURA' : ($codigos[$clave] ?? $codigos[(string) $mov->categoria] ?? null);
        if ($codigo === null) {
            return null;
        }
        $contra = $this->idCuentaCodigo($centroId, $codigo, $categoria, $mov, $libro);
        if ($contra === null) {
            return null;
        }
        $cents = abs($mov->importeCents);
        $debeTes = $mov->importeCents > 0 ? $cents : 0;
        $haberTes = $mov->importeCents < 0 ? $cents : 0;
        $glosa = trim(($nombresTercero[$mov->tercero] ?? '') . ($mov->nota !== '' ? ' — ' . $mov->nota : ''));

        return new Asiento(
            null,
            $ejercicioId,
            'G',
            null,
            new DateTimeImmutable($mov->fecha),
            $glosa !== '' ? $glosa : null,
            $apertura ? 'apertura' : 'normal',
            'import',
            null,
            [
                new Movimiento(null, 1, $tesoreria[$mov->cuenta], null, $debeTes, $haberTes),
                new Movimiento(null, 2, $contra, null, $haberTes, $debeTes),
            ],
        );
    }

    /** El nombre de Grisbi empieza por 60, 61, 70, 80 o 90; el número interno (44) no es la cuenta. */
    public static function grupoContable(string $nombre): ?string
    {
        if (preg_match('/(60|61|70|80|90)/', $nombre, $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    private function categoriaDe(LibroGrisbi $libro, int $numero): ?CategoriaGrisbi
    {
        foreach ($libro->categorias as $categoria) {
            if ($categoria->numero === $numero) {
                return $categoria;
            }
        }

        return null;
    }

    private function idCuentaCodigo(
        int $centroId,
        string $codigo,
        ?CategoriaGrisbi $categoria,
        MovimientoGrisbi $mov,
        LibroGrisbi $libro,
    ): ?int {
        $existente = $this->buscarCuenta($centroId, $codigo);
        if ($existente !== null) {
            return $existente;
        }
        $nombre = $codigo;
        $naturaleza = 'gasto';
        if ($codigo === 'APERTURA') {
            $nombre = 'Saldo inicial';
            $naturaleza = 'disponible';
        } else {
            foreach ($libro->subcategorias as $sub) {
                if ($mov->subcategoria > 0 && $sub->categoria === $mov->categoria && $sub->numero === $mov->subcategoria) {
                    $nombre = $sub->nombre;
                    break;
                }
            }
            if ($nombre === $codigo && $categoria !== null) {
                $nombre = $categoria->nombre !== '' ? $categoria->nombre : $codigo;
            }
            $naturaleza = $categoria !== null && $categoria->naturaleza === 'ingreso' ? 'ingreso' : 'gasto';
        }
        [$tipo, $nat, $imputable] = match ($naturaleza) {
            'ingreso' => ['ingreso', 'acreedora', true],
            'disponible' => ['patrimonio', 'acreedora', true],
            default => ['gasto', 'deudora', true],
        };
        AmbitoSeeder::upsertCuenta($this->pdo, [
            'centro_id' => $centroId,
            'persona_id' => null,
            'cuenta_fisica_id' => null,
            'padre_id' => null,
            'libro' => 'G',
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => $nombre,
            'tipo' => $tipo,
            'naturaleza' => $nat,
            'codigo_maestro' => $codigo,
            'imputable' => $imputable,
            'orden' => 100,
        ]);

        return $this->buscarCuenta($centroId, $codigo);
    }

    private function buscarCuenta(int $centroId, string $codigo): ?int
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND codigo = :codigo AND persona_id IS NULL
             LIMIT 1"
        );
        $st->execute([':c' => $centroId, ':codigo' => $codigo]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** @return list<string> */
    public function ejerciciosConApuntes(int $centroId, int $exceptoId): array
    {
        $st = $this->pdo->prepare(
            "SELECT e.etiqueta FROM ejercicios e
             WHERE e.centro_id = :c AND e.id <> :id
               AND EXISTS (
                 SELECT 1 FROM asientos a
                 WHERE a.ejercicio_id = e.id AND a.anulado_at IS NULL AND a.libro = 'G'
               )
             ORDER BY e.fecha_inicio"
        );
        $st->execute([':c' => $centroId, ':id' => $exceptoId]);

        return array_map(static fn (array $row): string => (string) $row['etiqueta'], $st->fetchAll());
    }

    private function ejercicioAbierto(int $centroId): int
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM ejercicios WHERE centro_id = :c AND estado = 'abierto' ORDER BY fecha_inicio DESC LIMIT 1"
        );
        $st->execute([':c' => $centroId]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : 0;
    }

    private function ejercicioDe(string $fecha, int $centroId): ?int
    {
        if ($centroId <= 0) {
            return null;
        }
        $st = $this->pdo->prepare(
            "SELECT id FROM ejercicios
             WHERE centro_id = :c AND fecha_inicio <= :f AND fecha_fin >= :f
             ORDER BY CASE WHEN estado = 'abierto' THEN 0 ELSE 1 END, fecha_inicio DESC
             LIMIT 1"
        );
        $st->execute([':c' => $centroId, ':f' => $fecha]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function centroDeTesoreria(int $cuentaId): int
    {
        $st = $this->pdo->prepare('SELECT centro_id FROM cuentas WHERE id = :id');
        $st->execute([':id' => $cuentaId]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : 0;
    }

    private function vincular(MovimientoGrisbi $mov, int $asientoId): void
    {
        $ejercicioId = $this->ejercicioDe($mov->fecha, $this->centroDelAsiento($asientoId));
        if ($ejercicioId === null) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO grisbi_vinculos (ejercicio_id, cuenta_grisbi, numero, asiento_id)
             VALUES (:e, :c, :n, :a)
             ON CONFLICT (ejercicio_id, cuenta_grisbi, numero) DO UPDATE
             SET asiento_id = EXCLUDED.asiento_id
             WHERE grisbi_vinculos.asiento_id IS NULL'
        );
        $st->execute([':e' => $ejercicioId, ':c' => $mov->cuenta, ':n' => $mov->numero, ':a' => $asientoId]);
    }

    private function centroDelAsiento(int $asientoId): int
    {
        $st = $this->pdo->prepare(
            'SELECT e.centro_id FROM asientos a JOIN ejercicios e ON e.id = a.ejercicio_id WHERE a.id = :id'
        );
        $st->execute([':id' => $asientoId]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : 0;
    }

    /**
     * @param array<string, string> $codigos
     * @param array<int, string> $nombresTercero
     */
    private function guardarListado(int $centroId, ListadoGrisbi $listado, array $codigos, array $nombresTercero): void
    {
        $nombre = trim($listado->nombre);
        if ($nombre === '') {
            return;
        }
        $categorias = [];
        foreach ($listado->categorias as $par) {
            $clave = $par['subcategoria'] !== null && $par['subcategoria'] > 0
                ? $par['categoria'] . '.' . $par['subcategoria']
                : (string) $par['categoria'];
            if (isset($codigos[$clave])) {
                $categorias[] = $codigos[$clave];
            }
        }
        $terceros = [];
        foreach ($listado->terceros as $numero) {
            if (isset($nombresTercero[$numero]) && $nombresTercero[$numero] !== '') {
                $terceros[] = $nombresTercero[$numero];
            }
        }
        $st = $this->pdo->prepare(
            'INSERT INTO listados (centro_id, nombre, mostrar_movimientos, mostrar_totales, periodo, fecha_desde, fecha_hasta, categorias, terceros)
             VALUES (:c, :n, :m, :t, :p, :d, :h, :cat, :ter)
             ON CONFLICT (centro_id, nombre) DO UPDATE SET
                mostrar_movimientos = excluded.mostrar_movimientos,
                mostrar_totales = excluded.mostrar_totales,
                periodo = excluded.periodo,
                fecha_desde = excluded.fecha_desde,
                fecha_hasta = excluded.fecha_hasta,
                categorias = excluded.categorias,
                terceros = excluded.terceros'
        );
        $st->execute([
            ':c' => $centroId,
            ':n' => $nombre,
            ':m' => $listado->mostrarMovimientos ? 1 : 0,
            ':t' => $listado->mostrarTotales ? 1 : 0,
            ':p' => ($listado->desde !== null || $listado->hasta !== null) ? 'otro' : 'actual',
            ':d' => $listado->desde,
            ':h' => $listado->hasta,
            ':cat' => json_encode(array_values(array_unique($categorias)), JSON_UNESCAPED_UNICODE),
            ':ter' => json_encode(array_values(array_unique($terceros)), JSON_UNESCAPED_UNICODE),
        ]);
    }
}
