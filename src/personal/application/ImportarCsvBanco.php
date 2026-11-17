<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use PDO;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\personal\domain\contracts\BancoImportRepository;
use src\importacion\domain\contracts\LectorExtractoEnFilas;
use src\importacion\domain\services\CatalogoBancosCsv;
use src\importacion\domain\services\HuellasExtracto;
use src\shared\domain\value_objects\Dinero;

final class ImportarCsvBanco
{
    private readonly AsentarLineaBancoPersonal $asentar;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ResolverPersonaActual $ambito,
        private readonly BancoImportRepository $filas,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsegurarPlanPersonal $plan,
    ) {
        $this->asentar = new AsentarLineaBancoPersonal($asientos, $cuentas, $ejercicios, $plan);
    }

    /**
     * @param list<list<string>>|null $filas Excel ya tabulado.
     * @return array{nuevos:int, repetidos:int, omitidos:int, pendientes:int}
     */
    public function ejecutar(string $banco, string $csv = '', ?array $filas = null): array
    {
        $banco = strtolower(trim($banco));
        $lector = CatalogoBancosCsv::lector($banco);
        if ($filas !== null) {
            if (!$lector instanceof LectorExtractoEnFilas) {
                throw new InvalidArgumentException(_("Este banco solo admite CSV"));
            }
            $lineas = $lector->leerFilas($filas);
        } else {
            $lineas = $lector->leer($csv);
        }
        $lineas = HuellasExtracto::distinguirIguales($lineas);
        $ctx = $this->ambito->ejecutar();
        $this->plan->ejecutar($ctx->centroId, $ctx->personaId);
        $tesoreriaId = $this->asentar->tesoreriaBanco($ctx->centroId, $ctx->personaId);

        $nuevos = 0;
        $repetidos = 0;
        $omitidos = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($lineas as $linea) {
                if ($this->filas->existe($ctx->personaId, $banco, $linea->huella)) {
                    $repetidos++;
                    continue;
                }
                try {
                    $asientoId = $this->asentar->ejecutar($ctx->centroId, $ctx->personaId, $tesoreriaId, $linea);
                } catch (InvalidArgumentException) {
                    $omitidos++;
                    continue;
                }
                $this->filas->guardar(
                    $ctx->personaId,
                    $banco,
                    $linea->huella,
                    $asientoId,
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
            'pendientes' => count($this->filas->deCuentas($ctx->personaId, [
                AsegurarPlanPersonal::CODIGO_PENDIENTE_GASTO,
                AsegurarPlanPersonal::CODIGO_PENDIENTE_INGRESO,
            ])),
        ];
    }
}
