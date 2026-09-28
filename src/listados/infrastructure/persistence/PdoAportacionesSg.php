<?php

declare(strict_types=1);

namespace src\listados\infrastructure\persistence;

use PDO;
use src\listados\domain\services\ListadoAportacionesSg;
use src\personas\infrastructure\persistence\PdoPersonaSg;
use src\shared\domain\value_objects\Dinero;

final class PdoAportacionesSg
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PdoPersonaSg $fichas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $centroId, int $ejercicioId, string $desde, string $hasta): array
    {
        $personas = [];
        $st = $this->pdo->prepare(
            'SELECT id, nombre, apellidos FROM personas
             WHERE centro_id = :c AND activo = TRUE
             ORDER BY apellidos, nombre'
        );
        $st->execute([':c' => $centroId]);
        $fichas = $this->fichas->deCentro($centroId);
        foreach ($st->fetchAll() as $row) {
            $id = (int) $row['id'];
            $ficha = $fichas[$id] ?? null;
            if ($ficha === null) {
                continue;
            }
            $personas[] = [
                'id' => $id,
                'nombre' => trim((string) $row['apellidos'] . ', ' . (string) $row['nombre'], ' ,'),
                'grupo' => $ficha['grupo'],
                'clase' => $ficha['clase'],
            ];
        }
        $armado = ListadoAportacionesSg::armar($personas, $this->importes($centroId, $ejercicioId, $desde, $hasta));

        return [
            'grupos' => array_map(fn (array $g): array => $this->grupo($g), $armado['grupos']),
            'cp' => array_map(fn (array $f): array => $this->filaCp($f), $armado['cp']),
            'total_cp' => self::linea($armado['total_cp']),
        ];
    }

    /** @return array<int, array<string, array<int, int>>> */
    private function importes(int $centroId, int $ejercicioId, string $desde, string $hasta): array
    {
        $sql = "SELECT COALESCE(m.persona_id, a.persona_id) AS persona_id,
                       c.codigo,
                       EXTRACT(MONTH FROM a.fecha)::int AS mes,
                       CASE
                         WHEN c.tipo IN ('ingreso', 'patrimonio') THEN COALESCE(SUM(m.haber - m.debe), 0)
                         WHEN c.tipo = 'gasto' THEN COALESCE(SUM(m.debe - m.haber), 0)
                         ELSE 0
                       END AS cents
                FROM cuentas c
                INNER JOIN movimientos m ON m.cuenta_id = c.id
                INNER JOIN asientos a ON a.id = m.asiento_id
                  AND a.ejercicio_id = :ej
                  AND a.libro = 'G'
                  AND a.anulado_at IS NULL
                  AND a.fecha >= :desde
                  AND a.fecha <= :hasta
                WHERE c.centro_id = :centro
                  AND c.libro = 'G'
                  AND c.codigo IN ('11', '12', '13')
                  AND COALESCE(m.persona_id, a.persona_id) IS NOT NULL
                GROUP BY COALESCE(m.persona_id, a.persona_id), c.codigo, EXTRACT(MONTH FROM a.fecha), c.tipo";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':centro' => $centroId,
            ':ej' => $ejercicioId,
            ':desde' => $desde,
            ':hasta' => $hasta,
        ]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $pid = (int) $row['persona_id'];
            $out[$pid][(string) $row['codigo']][(int) $row['mes']] = (int) $row['cents'];
        }

        return $out;
    }

    /** @param array{grupo:int, personas: list<array<string, mixed>>, total_ordinaria: list<int>, total_extraordinaria: list<int>} $g */
    private function grupo(array $g): array
    {
        return [
            'grupo' => $g['grupo'],
            'personas' => array_map(static function (array $p): array {
                return [
                    'id' => $p['id'],
                    'nombre' => $p['nombre'],
                    'ordinaria' => PdoAportacionesSg::linea($p['ordinaria']),
                    'extraordinaria' => PdoAportacionesSg::linea($p['extraordinaria']),
                ];
            }, $g['personas']),
            'total_ordinaria' => self::linea($g['total_ordinaria']),
            'total_extraordinaria' => self::linea($g['total_extraordinaria']),
        ];
    }

    /** @param array{id:int,nombre:string,grupo:int,meses:list<int>} $f */
    private function filaCp(array $f): array
    {
        return [
            'id' => $f['id'],
            'nombre' => $f['nombre'],
            'grupo' => $f['grupo'],
            'meses' => self::linea($f['meses']),
        ];
    }

    /**
     * @param list<int> $cents
     * @return array{meses: list<string>, total: string}
     */
    private static function linea(array $cents): array
    {
        $meses = [];
        $total = 0;
        foreach ($cents as $c) {
            $total += $c;
            $meses[] = $c === 0 ? '' : Dinero::fromCents($c)->formatEs();
        }

        return [
            'meses' => $meses,
            'total' => $total === 0 ? '' : Dinero::fromCents($total)->formatEs(),
        ];
    }
}
