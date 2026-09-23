<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use PDO;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\apuntes\domain\contracts\BancoCentroImportRepository;
use src\personal\domain\contracts\LectorExtractoEnFilas;
use src\personal\domain\services\CatalogoBancosCsv;
use src\shared\domain\value_objects\Dinero;

final class ImportarCsvBancoCentro
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ResolverAmbitoActual $ambito,
        private readonly BancoCentroImportRepository $filas,
        private readonly CuentaRepository $cuentas,
        private readonly CuentaFisicaRepository $fisicas,
        private readonly AsegurarPendienteBancoCentro $pendientes,
    ) {
    }

    /**
     * @param list<list<string>>|null $filasExcel Excel ya tabulado.
     * @return array{nuevos:int, repetidos:int, omitidos:int, pendientes:int}
     */
    public function ejecutar(string $banco, ?int $cuentaFisicaId = null, string $csv = '', ?array $filasExcel = null): array
    {
        $banco = strtolower(trim($banco));
        $lector = CatalogoBancosCsv::lector($banco);
        if ($filasExcel !== null) {
            if (!$lector instanceof LectorExtractoEnFilas) {
                throw new InvalidArgumentException(_('Este banco solo admite CSV'));
            }
            $lineas = $lector->leerFilas($filasExcel);
        } else {
            $lineas = $lector->leer($csv);
        }

        $ctx = $this->ambito->ejecutar();
        $centroId = $ctx->centroId;
        $this->pendientes->ejecutar($centroId);
        $tesoreria = $this->resolverTesoreria($centroId, $cuentaFisicaId);
        $fisicaId = $tesoreria->cuentaFisicaId;

        $nuevos = 0;
        $repetidos = 0;
        $omitidos = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($lineas as $linea) {
                if ($this->filas->existe($centroId, $fisicaId, $banco, $linea->huella)) {
                    $repetidos++;
                    continue;
                }
                $this->filas->guardar(
                    $centroId,
                    $fisicaId,
                    $banco,
                    $linea->huella,
                    $linea->fecha,
                    Dinero::fromCents($linea->cents)->toString(),
                    $linea->concepto,
                );
                $nuevos++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'nuevos' => $nuevos,
            'repetidos' => $repetidos,
            'omitidos' => $omitidos,
            'pendientes' => count($this->filas->sinAsentar($centroId))
                + count($this->filas->deCuentas($centroId, [
                    AsegurarPendienteBancoCentro::CODIGO_PENDIENTE_GASTO,
                    AsegurarPendienteBancoCentro::CODIGO_PENDIENTE_INGRESO,
                ])),
        ];
    }

    private function resolverTesoreria(int $centroId, ?int $cuentaFisicaId): Cuenta
    {
        if ($cuentaFisicaId !== null) {
            $fisica = $this->fisicas->porId($cuentaFisicaId);
            if ($fisica === null || $fisica->centroId !== $centroId || !$fisica->activo || $fisica->tipo !== 'banco') {
                throw new InvalidArgumentException(_('Cuenta de banco no válida'));
            }
            $cuenta = $this->cuentas->tesoreriaDeFisica($centroId, 'G', $cuentaFisicaId);
            if ($cuenta === null || $cuenta->id === null) {
                throw new InvalidArgumentException(_('No hay cuenta BANCO del libro G para esa cuenta física'));
            }

            return $cuenta;
        }

        $activas = $this->fisicas->listarActivasDeCentro($centroId, 'banco');
        if (count($activas) > 1) {
            throw new InvalidArgumentException(_('Hay varios bancos activos: indique la cuenta de tesorería'));
        }
        if (count($activas) === 1 && $activas[0]->id !== null) {
            $cuenta = $this->cuentas->tesoreriaDeFisica($centroId, 'G', $activas[0]->id);
            if ($cuenta !== null && $cuenta->id !== null) {
                return $cuenta;
            }
        }

        $cuenta = $this->cuentas->tesoreria($centroId, 'G', 'BANCO');
        if ($cuenta === null || $cuenta->id === null) {
            throw new InvalidArgumentException(_('No hay cuenta BANCO del libro G'));
        }

        return $cuenta;
    }
}
