<?php

declare(strict_types=1);

namespace src\asientos\application;

use PDO;
use src\ambito\domain\contracts\CuentaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\asientos\domain\services\ReparadorTraspasoAFilaGastoCentroSg;
use src\plan\domain\services\CatalogoPlanesContables;

final class RepararAsientosTraspasoCentroSg
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    /**
     * @return array{reparados:int,omitidos:int,detalles:list<string>}
     */
    public function ejecutar(bool $dryRun = true, ?string $codigoCentro = null): array
    {
        $reparados = 0;
        $omitidos = 0;
        $detalles = [];

        foreach ($this->candidatos($codigoCentro) as $row) {
            $asientoId = (int) $row['asiento_id'];
            $centroId = (int) $row['centro_id'];
            $asiento = $this->asientos->porId($asientoId);
            if ($asiento === null || $asiento->tipo !== 'traspaso' || $asiento->libro !== 'G') {
                ++$omitidos;
                continue;
            }

            $legs = $this->legsConCuenta($asientoId);
            $propuesta = ReparadorTraspasoAFilaGastoCentroSg::proponer($legs);
            if ($propuesta === null) {
                ++$omitidos;
                $detalles[] = sprintf('omitido asiento %d: no encaja el patrón caja/banco', $asientoId);
                continue;
            }

            $gasto = $this->cuentas->buscar($centroId, null, 'G', $propuesta['concepto_codigo']);
            if ($gasto === null || $gasto->id === null || $gasto->tipo !== 'gasto') {
                ++$omitidos;
                $detalles[] = sprintf(
                    'omitido asiento %d: falta cuenta G/%s imputable de gasto',
                    $asientoId,
                    $propuesta['concepto_codigo'],
                );
                continue;
            }

            $importe = $propuesta['importe'];
            $cajaId = $propuesta['caja_cuenta_id'];
            $nuevo = new Asiento(
                $asiento->id,
                $asiento->ejercicioId,
                $asiento->libro,
                $asiento->numero,
                $asiento->fecha,
                $asiento->glosa,
                'normal',
                $asiento->origen,
                $asiento->personaId,
                [
                    new Movimiento(null, 1, $gasto->id, $asiento->personaId, $importe, 0),
                    new Movimiento(null, 2, $cajaId, $asiento->personaId, 0, $importe),
                ],
                $propuesta['concepto_codigo'],
                $asiento->asientoParId,
                $asiento->fechaOperacion(),
                $asiento->remesaId,
                $asiento->gastoGenerales,
                $asiento->conceptoGenerales,
                $asiento->plantillaApunteId,
            );

            if (!$dryRun) {
                $this->asientos->actualizar($nuevo, true);
            }
            ++$reparados;
            $detalles[] = sprintf(
                '%s asiento %d (%s): G/%s %.2f € + caja',
                $dryRun ? 'simulado' : 'reparado',
                $asientoId,
                (string) $row['centro_codigo'],
                $propuesta['concepto_codigo'],
                $importe / 100,
            );
        }

        return ['reparados' => $reparados, 'omitidos' => $omitidos, 'detalles' => $detalles];
    }

    /**
     * @return list<array{asiento_id:int,centro_id:int,centro_codigo:string}>
     */
    private function candidatos(?string $codigoCentro): array
    {
        $sql = "SELECT a.id AS asiento_id, ce.id AS centro_id, ce.codigo AS centro_codigo
                FROM asientos a
                INNER JOIN ejercicios e ON e.id = a.ejercicio_id
                INNER JOIN centros ce ON ce.id = e.centro_id
                INNER JOIN planes_contables p ON p.id = ce.plan_contable_id
                WHERE p.codigo = :plan
                  AND a.libro = 'G'
                  AND a.tipo = 'traspaso'
                  AND a.anulado_at IS NULL";
        $params = [':plan' => CatalogoPlanesContables::CENTRO_SG];
        if ($codigoCentro !== null && $codigoCentro !== '') {
            $sql .= ' AND ce.codigo = :centro';
            $params[':centro'] = $codigoCentro;
        }
        $sql .= ' ORDER BY ce.codigo, a.fecha, a.numero, a.id';

        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{codigo_maestro:string,tipo:string,debe:int,haber:int,cuenta_id:int,codigo:string}>
     */
    private function legsConCuenta(int $asientoId): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.codigo_maestro, c.tipo, c.codigo, m.debe, m.haber, m.cuenta_id
             FROM movimientos m
             INNER JOIN cuentas c ON c.id = m.cuenta_id
             WHERE m.asiento_id = :a
             ORDER BY m.orden'
        );
        $st->execute([':a' => $asientoId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'codigo_maestro' => (string) $row['codigo_maestro'],
                'tipo' => (string) $row['tipo'],
                'codigo' => (string) $row['codigo'],
                'debe' => (int) $row['debe'],
                'haber' => (int) $row['haber'],
                'cuenta_id' => (int) $row['cuenta_id'],
            ];
        }

        return $out;
    }
}
