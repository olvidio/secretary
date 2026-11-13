<?php

declare(strict_types=1);

namespace src\listados\infrastructure\persistence;

use InvalidArgumentException;
use PDO;
use src\listados\domain\services\GruposGlosa;
use src\listados\domain\services\PeriodoListado;
use src\plan\domain\services\CatalogoPlanesContables;

final class PdoListadoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listar(int $centroId): array
    {
        $this->exigirClub($centroId);
        $st = $this->pdo->prepare(
            'SELECT id, nombre, mostrar_movimientos, mostrar_totales, periodo, fecha_desde, fecha_hasta,
                    categorias, terceros, cuentas_tesoreria, incluir_traspasos, excluir_nulos, texto,
                    columnas, agrupar, separar_signo, separar_periodo, orden, orden_desc, incluir_saldos, grupos_glosa, detalle_grupos
             FROM listados WHERE centro_id = :c ORDER BY nombre'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = $this->fila($row);
        }

        return $out;
    }

    /** @param array<string, mixed> $datos */
    public function guardar(int $centroId, array $datos): array
    {
        $this->exigirClub($centroId);
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException(_('El listado necesita un nombre'));
        }
        $id = isset($datos['id']) ? (int) $datos['id'] : 0;
        $categorias = $this->textos($datos['categorias'] ?? []);
        $terceros = $this->textos($datos['terceros'] ?? []);
        $periodo = (string) ($datos['periodo'] ?? 'actual');
        if (!in_array($periodo, PeriodoListado::VALIDOS, true)) {
            throw new InvalidArgumentException(_('El periodo no es válido'));
        }
        $desde = $periodo === 'otro' ? $this->fecha($datos['fecha_desde'] ?? null) : null;
        $hasta = $periodo === 'otro' ? $this->fecha($datos['fecha_hasta'] ?? null) : null;
        $params = [
            ':n' => $nombre,
            ':m' => !empty($datos['mostrar_movimientos']) ? 1 : 0,
            ':t' => !empty($datos['mostrar_totales']) ? 1 : 0,
            ':p' => $periodo,
            ':d' => $desde,
            ':h' => $hasta,
            ':cat' => json_encode($categorias, JSON_UNESCAPED_UNICODE),
            ':ter' => json_encode($terceros, JSON_UNESCAPED_UNICODE),
            ':tes' => json_encode($this->textos($datos['cuentas_tesoreria'] ?? []), JSON_UNESCAPED_UNICODE),
            ':tr' => !array_key_exists('incluir_traspasos', $datos) || !empty($datos['incluir_traspasos']) ? 1 : 0,
            ':en' => !empty($datos['excluir_nulos']) ? 1 : 0,
            ':tx' => trim((string) ($datos['texto'] ?? '')) !== '' ? trim((string) $datos['texto']) : null,
            ':col' => json_encode($this->columnas($datos['columnas'] ?? null), JSON_UNESCAPED_UNICODE),
            ':ag' => json_encode($this->agrupar($datos['agrupar'] ?? []), JSON_UNESCAPED_UNICODE),
            ':ss' => !empty($datos['separar_signo']) ? 1 : 0,
            ':sp' => $this->separarPeriodo($datos['separar_periodo'] ?? ''),
            ':ord' => $this->orden($datos['orden'] ?? 'fecha'),
            ':od' => !empty($datos['orden_desc']) ? 1 : 0,
            ':sal' => !empty($datos['incluir_saldos']) ? 1 : 0,
            ':dg' => !empty($datos['detalle_grupos']) ? 1 : 0,
            ':gg' => json_encode(GruposGlosa::normalizar($datos['grupos_glosa'] ?? []), JSON_UNESCAPED_UNICODE),
        ];
        if ($id > 0) {
            $params[':id'] = $id;
            $params[':c'] = $centroId;
            $st = $this->pdo->prepare(
                'UPDATE listados SET nombre = :n, mostrar_movimientos = :m, mostrar_totales = :t,
                    periodo = :p, fecha_desde = :d, fecha_hasta = :h, categorias = :cat, terceros = :ter,
                    cuentas_tesoreria = :tes, incluir_traspasos = :tr, excluir_nulos = :en, texto = :tx,
                    columnas = :col, agrupar = :ag, separar_signo = :ss, separar_periodo = :sp,
                    orden = :ord, orden_desc = :od, incluir_saldos = :sal, grupos_glosa = :gg, detalle_grupos = :dg
                 WHERE id = :id AND centro_id = :c'
            );
            $st->execute($params);
        } else {
            $params[':c'] = $centroId;
            $st = $this->pdo->prepare(
                'INSERT INTO listados (centro_id, nombre, mostrar_movimientos, mostrar_totales, periodo, fecha_desde, fecha_hasta, categorias, terceros, cuentas_tesoreria, incluir_traspasos, excluir_nulos, texto, columnas, agrupar, separar_signo, separar_periodo, orden, orden_desc, incluir_saldos, grupos_glosa, detalle_grupos)
                 VALUES (:c, :n, :m, :t, :p, :d, :h, :cat, :ter, :tes, :tr, :en, :tx, :col, :ag, :ss, :sp, :ord, :od, :sal, :gg, :dg)
                 RETURNING id'
            );
            $st->execute($params);
            $id = (int) $st->fetchColumn();
        }

        return $this->porId($centroId, $id);
    }

    public function borrar(int $centroId, int $id): void
    {
        $this->exigirClub($centroId);
        $st = $this->pdo->prepare('DELETE FROM listados WHERE id = :id AND centro_id = :c');
        $st->execute([':id' => $id, ':c' => $centroId]);
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $centroId, int $id): array
    {
        $guardado = $this->porId($centroId, $id);
        // Mes actual o anterior: se resuelve hoy a sus fechas y sigue como «otras fechas».
        $listado = $guardado;
        if (PeriodoListado::esMes($guardado['periodo'])) {
            $mes = PeriodoListado::fechasDelMes($guardado['periodo'], new \DateTimeImmutable('today'));
            $listado['periodo'] = 'otro';
            $listado['fecha_desde'] = $mes['desde'];
            $listado['fecha_hasta'] = $mes['hasta'];
        }
        $categorias = $listado['categorias'];
        $terceros = $listado['terceros'];
        $sql = "SELECT a.fecha::text AS fecha, COALESCE(a.glosa, '') AS glosa, c.codigo, c.nombre, c.tipo,
                    m.debe AS debe, m.haber AS haber,
                    (SELECT string_agg(ct.nombre, ', ' ORDER BY ct.codigo)
                     FROM movimientos mt JOIN cuentas ct ON ct.id = mt.cuenta_id
                     WHERE mt.asiento_id = a.id AND ct.tipo = 'tesoreria') AS tesoreria
             FROM movimientos m
             JOIN asientos a ON a.id = m.asiento_id
             JOIN ejercicios e ON e.id = a.ejercicio_id
             JOIN cuentas c ON c.id = m.cuenta_id
             WHERE e.centro_id = :c AND a.libro = 'G' AND a.anulado_at IS NULL
               AND c.tipo IN ('ingreso', 'gasto')";
        $params = [':c' => $centroId];
        if ($listado['periodo'] === 'otro') {
            if ($listado['fecha_desde'] !== null) {
                $sql .= ' AND a.fecha >= :d';
                $params[':d'] = $listado['fecha_desde'];
            }
            if ($listado['fecha_hasta'] !== null) {
                $sql .= ' AND a.fecha <= :h';
                $params[':h'] = $listado['fecha_hasta'];
            }
        } else {
            $ejercicioId = $this->ejercicioDePeriodo($centroId, $listado['periodo']);
            if ($ejercicioId !== null) {
                $sql .= ' AND a.ejercicio_id = :ej';
                $params[':ej'] = $ejercicioId;
            }
        }
        if ($listado['cuentas_tesoreria'] !== []) {
            $marcas = [];
            foreach ($listado['cuentas_tesoreria'] as $i => $codigo) {
                $clave = ':tes' . $i;
                $marcas[] = $clave;
                $params[$clave] = $codigo;
            }
            $sql .= ' AND EXISTS (
                SELECT 1 FROM movimientos mt
                JOIN cuentas ct ON ct.id = mt.cuenta_id
                WHERE mt.asiento_id = a.id AND ct.tipo = \'tesoreria\' AND ct.codigo IN (' . implode(',', $marcas) . ')
            )';
        }
        if (!$listado['incluir_traspasos']) {
            $sql .= " AND a.tipo <> 'traspaso'";
        }
        $sql .= ' ORDER BY a.fecha, a.numero, m.orden';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $grupos = $this->gruposDe($centroId);
        $porGlosa = in_array('glosa', $listado['agrupar'], true);
        $filas = [];
        foreach ($st->fetchAll() as $row) {
            if ($categorias !== [] && !$this->coincideCodigo((string) $row['codigo'], $categorias)) {
                continue;
            }
            if ($terceros !== [] && !$this->coincideTercero((string) $row['glosa'], $terceros)) {
                continue;
            }
            if ($listado['texto'] !== '' && !str_contains(mb_strtolower((string) $row['glosa']), mb_strtolower($listado['texto']))) {
                continue;
            }
            if ($listado['excluir_nulos'] && (int) $row['debe'] === 0 && (int) $row['haber'] === 0) {
                continue;
            }
            $debe = (int) $row['debe'];
            $haber = (int) $row['haber'];
            // Sin grupo que case, el movimiento va al final, en «Otros» (nombre vacío).
            $indiceGlosa = $porGlosa ? GruposGlosa::indiceDe((string) $row['glosa'], $listado['grupos_glosa']) : null;
            $filas[] = [
                'fecha' => (string) $row['fecha'],
                'glosa' => (string) $row['glosa'],
                'codigo' => (string) $row['codigo'],
                'nombre' => (string) $row['nombre'],
                'tesoreria' => (string) ($row['tesoreria'] ?? ''),
                'debe' => $debe,
                'haber' => $haber,
                'tipo' => (string) $row['tipo'],
                'signo' => $this->signoDe((string) $row['codigo'], (string) $row['tipo'], $grupos),
                'periodo_grupo' => $this->etiquetaGrupo((string) $row['fecha'], $listado['separar_periodo']),
                'periodo_orden' => $this->ordenGrupo((string) $row['fecha'], $listado['separar_periodo']),
                'grupo_glosa' => $indiceGlosa !== null ? $listado['grupos_glosa'][$indiceGlosa]['nombre'] : '',
                'grupo_glosa_orden' => sprintf('%04d', $indiceGlosa ?? 9999),
            ];
        }
        $this->ordenar($filas, $listado);
        $totales = [];
        foreach ($filas as $fila) {
            $codigo = $fila['codigo'];
            if (!isset($totales[$codigo])) {
                $totales[$codigo] = ['codigo' => $codigo, 'nombre' => $fila['nombre'], 'importe' => 0];
            }
            $totales[$codigo]['importe'] += $fila['tipo'] === 'gasto'
                ? $fila['debe'] - $fila['haber']
                : $fila['haber'] - $fila['debe'];
        }

        return [
            'listado' => $guardado,
            'periodo' => $this->etiquetaPeriodo($centroId, $listado),
            'movimientos' => $listado['mostrar_movimientos'] ? $filas : [],
            'totales' => $listado['mostrar_totales'] ? array_values($totales) : [],
            'grupos' => array_map(static fn (array $g): string => $g['nombre'], $grupos),
            'saldos' => $listado['incluir_saldos'] ? $this->saldos($centroId, $listado) : null,
        ];
    }

    /**
     * Saldo de caja y banco al empezar y al acabar el periodo del listado, en céntimos.
     * El inicial es la apertura del ejercicio (más lo movido antes de la fecha «desde»);
     * el final sale de las cuentas de tesorería, no de sumar el listado, así que sigue
     * siendo el dinero real aunque haya traspasos. Null si el listado filtra por
     * categorías, terceros o texto (el saldo no sería el de esas líneas) o si el
     * periodo no cae dentro de un solo ejercicio.
     *
     * @param array<string, mixed> $listado
     * @return array{inicial:int,final:int}|null
     */
    private function saldos(int $centroId, array $listado): ?array
    {
        if ($listado['categorias'] !== [] || $listado['terceros'] !== [] || $listado['texto'] !== '') {
            return null;
        }
        $desde = null;
        $hasta = null;
        if ($listado['periodo'] === 'otro') {
            $desde = $listado['fecha_desde'];
            $hasta = $listado['fecha_hasta'];
            if ($desde === null) {
                return null;
            }
            $st = $this->pdo->prepare(
                'SELECT id FROM ejercicios
                 WHERE centro_id = :c AND fecha_inicio <= :d AND fecha_fin >= :d
                   AND (CAST(:h AS date) IS NULL OR fecha_fin >= CAST(:h AS date))
                 ORDER BY fecha_inicio DESC LIMIT 1'
            );
            $st->execute([':c' => $centroId, ':d' => $desde, ':h' => $hasta]);
            $id = $st->fetchColumn();
            $ejercicioId = $id !== false ? (int) $id : null;
        } else {
            $ejercicioId = $this->ejercicioDePeriodo($centroId, $listado['periodo']);
        }
        if ($ejercicioId === null) {
            return null;
        }
        $params = [':ej' => $ejercicioId, ':d' => $desde, ':h' => $hasta];
        $filtro = '';
        if ($listado['cuentas_tesoreria'] !== []) {
            $marcas = [];
            foreach (array_values($listado['cuentas_tesoreria']) as $i => $codigo) {
                $marcas[] = ':t' . $i;
                $params[':t' . $i] = $codigo;
            }
            $filtro = ' AND c.codigo IN (' . implode(',', $marcas) . ')';
        }
        $st = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN a.tipo = 'apertura'
                        OR (CAST(:d AS date) IS NOT NULL AND a.fecha < CAST(:d AS date))
                    THEN m.debe - m.haber ELSE 0 END), 0) AS inicial,
                COALESCE(SUM(CASE WHEN CAST(:h AS date) IS NULL OR a.fecha <= CAST(:h AS date)
                    THEN m.debe - m.haber ELSE 0 END), 0) AS final
             FROM movimientos m
             JOIN asientos a ON a.id = m.asiento_id
             JOIN cuentas c ON c.id = m.cuenta_id
             WHERE a.ejercicio_id = :ej AND a.libro = 'G' AND a.anulado_at IS NULL
               AND c.tipo = 'tesoreria'" . $filtro
        );
        $st->execute($params);
        $row = $st->fetch();

        return ['inicial' => (int) $row['inicial'], 'final' => (int) $row['final']];
    }

    /**
     * Ingresos o gastos según la naturaleza de la categoría, no el importe del movimiento.
     * Una hija sigue a su madre: 90.1 Ingressos va en Gastos porque 90 Aiguafreda es de gasto.
     *
     * @param array<string, array{nombre:string,tipo:string}> $grupos
     */
    private function signoDe(string $codigo, string $tipo, array $grupos): string
    {
        $punto = strpos($codigo, '.');
        $madre = $punto !== false ? substr($codigo, 0, $punto) : '';
        if ($madre !== '' && isset($grupos[$madre])) {
            $tipo = $grupos[$madre]['tipo'];
        }

        return $tipo === 'ingreso' ? 'ingreso' : 'gasto';
    }

    /**
     * Categorías madre (60, 61…): titulan a sus hijas y les dan la naturaleza.
     *
     * @return array<string, array{nombre:string,tipo:string}> por código
     */
    private function gruposDe(int $centroId): array
    {
        $st = $this->pdo->prepare(
            "SELECT codigo, nombre, tipo FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND persona_id IS NULL
               AND tipo IN ('ingreso', 'gasto') AND imputable = FALSE AND activo = TRUE"
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(string) $row['codigo']] = ['nombre' => (string) $row['nombre'], 'tipo' => (string) $row['tipo']];
        }

        return $out;
    }

    /** @return list<array{codigo:string,nombre:string,grupo:bool}> */
    public function cuentas(int $centroId): array
    {
        $this->exigirClub($centroId);
        $st = $this->pdo->prepare(
            "SELECT codigo, nombre, imputable FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND persona_id IS NULL
               AND tipo IN ('ingreso', 'gasto') AND activo = TRUE
               AND codigo ~ '^[0-9]{2}(\\.[0-9]+)?$'
             ORDER BY codigo"
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'codigo' => (string) $row['codigo'],
                'nombre' => (string) $row['nombre'],
                'grupo' => !$row['imputable'],
            ];
        }

        return $out;
    }

    /** @return list<array{codigo:string,nombre:string}> */
    public function tesoreria(int $centroId): array
    {
        $this->exigirClub($centroId);
        $st = $this->pdo->prepare(
            "SELECT codigo, nombre FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND persona_id IS NULL
               AND tipo = 'tesoreria' AND activo = TRUE
             ORDER BY codigo"
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = ['codigo' => (string) $row['codigo'], 'nombre' => (string) $row['nombre']];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function porId(int $centroId, int $id): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, nombre, mostrar_movimientos, mostrar_totales, periodo, fecha_desde, fecha_hasta,
                    categorias, terceros, cuentas_tesoreria, incluir_traspasos, excluir_nulos, texto,
                    columnas, agrupar, separar_signo, separar_periodo, orden, orden_desc, incluir_saldos, grupos_glosa, detalle_grupos
             FROM listados WHERE id = :id AND centro_id = :c'
        );
        $st->execute([':id' => $id, ':c' => $centroId]);
        $row = $st->fetch();
        if (!is_array($row)) {
            throw new InvalidArgumentException(_('Listado no encontrado'));
        }

        return $this->fila($row);
    }

    /** @param array<string, mixed> $row */
    private function fila(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'nombre' => (string) $row['nombre'],
            'mostrar_movimientos' => (bool) $row['mostrar_movimientos'],
            'mostrar_totales' => (bool) $row['mostrar_totales'],
            'periodo' => in_array($row['periodo'] ?? '', PeriodoListado::VALIDOS, true) ? $row['periodo'] : 'actual',
            'fecha_desde' => $row['fecha_desde'] !== null ? (string) $row['fecha_desde'] : null,
            'fecha_hasta' => $row['fecha_hasta'] !== null ? (string) $row['fecha_hasta'] : null,
            'categorias' => $this->jsonLista($row['categorias']),
            'terceros' => $this->jsonLista($row['terceros']),
            'cuentas_tesoreria' => $this->jsonLista($row['cuentas_tesoreria'] ?? '[]'),
            'incluir_traspasos' => !isset($row['incluir_traspasos']) || (bool) $row['incluir_traspasos'],
            'excluir_nulos' => (bool) ($row['excluir_nulos'] ?? false),
            'texto' => isset($row['texto']) && $row['texto'] !== null ? (string) $row['texto'] : '',
            'columnas' => $this->columnas($row['columnas'] ?? null),
            'agrupar' => $this->agrupar($this->jsonLista($row['agrupar'] ?? '[]')),
            'separar_signo' => (bool) ($row['separar_signo'] ?? false),
            'separar_periodo' => $this->separarPeriodo($row['separar_periodo'] ?? ''),
            'orden' => $this->orden($row['orden'] ?? 'fecha'),
            'orden_desc' => (bool) ($row['orden_desc'] ?? false),
            'incluir_saldos' => (bool) ($row['incluir_saldos'] ?? false),
            'detalle_grupos' => (bool) ($row['detalle_grupos'] ?? false),
            'grupos_glosa' => $this->gruposGlosaGuardados($row['grupos_glosa'] ?? '[]'),
        ];
    }

    /**
     * Los grupos ya se validaron al guardar; si aun así el dato no sirve, el listado sale sin ellos.
     *
     * @return list<array{nombre:string,patron:string}>
     */
    private function gruposGlosaGuardados(mixed $valor): array
    {
        try {
            return GruposGlosa::normalizar($valor);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /** @param mixed $valor @return list<string> */
    private function jsonLista(mixed $valor): array
    {
        if (is_string($valor)) {
            $decoded = json_decode($valor, true);
            $valor = is_array($decoded) ? $decoded : [];
        }

        return is_array($valor) ? $this->textos($valor) : [];
    }

    /** @param mixed $valor @return list<string> */
    private function textos(mixed $valor): array
    {
        if (!is_array($valor)) {
            return [];
        }
        $out = [];
        foreach ($valor as $item) {
            $texto = trim((string) $item);
            if ($texto !== '') {
                $out[] = $texto;
            }
        }

        return array_values(array_unique($out));
    }

    /** @param array<string, mixed> $listado */
    private function etiquetaPeriodo(int $centroId, array $listado): string
    {
        if ($listado['periodo'] === 'otro') {
            $desde = $this->fechaEs($listado['fecha_desde']);
            $hasta = $this->fechaEs($listado['fecha_hasta']);
            if ($desde !== '' && $hasta !== '') {
                return $desde . ' – ' . $hasta;
            }

            return $desde !== '' ? $desde : $hasta;
        }
        $id = $this->ejercicioDePeriodo($centroId, (string) $listado['periodo']);
        if ($id === null) {
            return '';
        }
        $st = $this->pdo->prepare('SELECT fecha_inicio::text AS ini, fecha_fin::text AS fin FROM ejercicios WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!is_array($row)) {
            return '';
        }
        $desde = $this->fechaEs($row['ini']);
        $hasta = $this->fechaEs($row['fin']);

        return $desde !== '' && $hasta !== '' ? $desde . ' – ' . $hasta : $desde;
    }

    private function fechaEs(mixed $iso): string
    {
        $texto = trim((string) $iso);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $texto, $m) !== 1) {
            return '';
        }

        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }

    private function ejercicioDePeriodo(int $centroId, string $periodo): ?int
    {
        $st = $this->pdo->prepare(
            "SELECT id, fecha_inicio FROM ejercicios
             WHERE centro_id = :c AND estado = 'abierto'
             ORDER BY fecha_inicio DESC LIMIT 1"
        );
        $st->execute([':c' => $centroId]);
        $abierto = $st->fetch();
        if (!is_array($abierto)) {
            return null;
        }
        if ($periodo !== 'anterior') {
            return (int) $abierto['id'];
        }
        $ant = $this->pdo->prepare(
            'SELECT id FROM ejercicios
             WHERE centro_id = :c AND fecha_fin < :ini
             ORDER BY fecha_fin DESC LIMIT 1'
        );
        $ant->execute([':c' => $centroId, ':ini' => $abierto['fecha_inicio']]);
        $id = $ant->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function fecha(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $texto);
        if ($dt === false) {
            throw new InvalidArgumentException(_('Fecha no válida'));
        }

        return $dt->format('Y-m-d');
    }

    /** @param list<string> $categorias */
    private function coincideCodigo(string $codigo, array $categorias): bool
    {
        foreach ($categorias as $filtro) {
            if ($codigo === $filtro || str_starts_with($codigo, $filtro . '.')) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $terceros */
    private function coincideTercero(string $glosa, array $terceros): bool
    {
        $glosa = mb_strtolower($glosa);
        foreach ($terceros as $tercero) {
            if ($tercero !== '' && str_contains($glosa, mb_strtolower($tercero))) {
                return true;
            }
        }

        return false;
    }

    /** @param mixed $valor @return list<string> */
    private function columnas(mixed $valor): array
    {
        $permitidas = ['fecha', 'tesoreria', 'categoria', 'glosa', 'debe', 'haber', 'importe'];
        $lista = $valor === null ? ['fecha', 'categoria', 'glosa', 'debe', 'haber'] : $this->jsonLista($valor);
        $out = [];
        foreach ($permitidas as $columna) {
            if (in_array($columna, $lista, true)) {
                $out[] = $columna;
            }
        }

        return $out !== [] ? $out : ['fecha', 'categoria', 'glosa', 'debe', 'haber'];
    }

    /** @param mixed $valor @return list<string> */
    private function agrupar(mixed $valor): array
    {
        $lista = is_array($valor) ? $this->textos($valor) : $this->jsonLista($valor);
        $out = [];
        foreach ($lista as $nivel) {
            if (in_array($nivel, ['categoria', 'tesoreria', 'glosa'], true) && !in_array($nivel, $out, true)) {
                $out[] = $nivel;
            }
        }

        return $out;
    }

    private function separarPeriodo(mixed $valor): string
    {
        $texto = (string) $valor;

        return in_array($texto, ['dia', 'semana', 'mes', 'ano'], true) ? $texto : '';
    }

    private function orden(mixed $valor): string
    {
        $texto = (string) $valor;

        return in_array($texto, ['fecha', 'glosa', 'importe'], true) ? $texto : 'fecha';
    }

    private function ordenGrupo(string $fecha, string $periodo): string
    {
        if ($periodo === '' || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $m) !== 1) {
            return '';
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $m[1] . '-' . $m[2] . '-' . $m[3]);
        if ($dt === false) {
            return '';
        }
        if ($periodo === 'semana') {
            return $dt->setISODate((int) $dt->format('o'), (int) $dt->format('W'))->format('Y-m-d');
        }
        if ($periodo === 'mes') {
            return $dt->format('Y-m');
        }
        if ($periodo === 'ano') {
            return $dt->format('Y');
        }

        return $dt->format('Y-m-d');
    }

    private function etiquetaGrupo(string $fecha, string $periodo): string
    {
        if ($periodo === '' || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $m) !== 1) {
            return '';
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $m[1] . '-' . $m[2] . '-' . $m[3]);
        if ($dt === false) {
            return '';
        }
        if ($periodo === 'dia') {
            return $dt->format('d/m/Y');
        }
        if ($periodo === 'semana') {
            $lunes = $dt->setISODate((int) $dt->format('o'), (int) $dt->format('W'));

            return _('Semana del') . ' ' . $lunes->format('d/m/Y');
        }
        if ($periodo === 'ano') {
            return $dt->format('Y');
        }
        $meses = ['01' => _('enero'), '02' => _('febrero'), '03' => _('marzo'), '04' => _('abril'), '05' => _('mayo'), '06' => _('junio'), '07' => _('julio'), '08' => _('agosto'), '09' => _('septiembre'), '10' => _('octubre'), '11' => _('noviembre'), '12' => _('diciembre')];

        return ($meses[$m[2]] ?? $m[2]) . ' ' . $m[1];
    }

    /** @param list<array<string, mixed>> $filas @param array<string, mixed> $listado */
    private function ordenar(array &$filas, array $listado): void
    {
        $desc = !empty($listado['orden_desc']);
        $orden = (string) $listado['orden'];
        $niveles = [];
        if ($listado['separar_periodo'] !== '') {
            $niveles[] = 'periodo_orden';
        }
        if (!empty($listado['separar_signo'])) {
            $niveles[] = 'signo';
        }
        foreach ($listado['agrupar'] as $nivel) {
            $niveles[] = match ($nivel) {
                'categoria' => 'codigo',
                'glosa' => 'grupo_glosa_orden',
                default => 'tesoreria',
            };
        }
        usort($filas, function (array $a, array $b) use ($niveles, $orden, $desc): int {
            foreach ($niveles as $nivel) {
                $cmp = $nivel === 'signo'
                    ? (($a['signo'] === 'ingreso' ? 0 : 1) <=> ($b['signo'] === 'ingreso' ? 0 : 1))
                    : strcmp((string) $a[$nivel], (string) $b[$nivel]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            $cmp = match ($orden) {
                'glosa' => strcasecmp((string) $a['glosa'], (string) $b['glosa']),
                'importe' => ($a['haber'] - $a['debe']) <=> ($b['haber'] - $b['debe']),
                default => strcmp((string) $a['fecha'], (string) $b['fecha']),
            };
            if ($cmp === 0) {
                $cmp = strcmp((string) $a['fecha'], (string) $b['fecha']);
            }

            return $desc ? -$cmp : $cmp;
        });
    }

    private function exigirClub(int $centroId): void
    {
        $st = $this->pdo->prepare(
            "SELECT COALESCE(p.codigo, '') FROM centros c
             LEFT JOIN planes_contables p ON p.id = c.plan_contable_id WHERE c.id = :id"
        );
        $st->execute([':id' => $centroId]);
        $codigo = $st->fetchColumn();
        if (!is_string($codigo) || !CatalogoPlanesContables::esClub($codigo)) {
            throw new InvalidArgumentException(_('Los listados del club solo están en un centro con plan Club'));
        }
    }
}
