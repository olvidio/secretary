<?php

declare(strict_types=1);

namespace src\personal\application;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\personal\domain\contracts\BancoImportRepository;
use src\personal\domain\services\CatalogoBancosCsv;
use src\personal\domain\services\ConstructorAsientoPersonal;
use src\personal\domain\services\LectorCsvCaixaBank;
use src\personal\domain\value_objects\LineaExtractoBanco;
use src\shared\domain\value_objects\Dinero;

final class ImportarCsvBanco
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ResolverPersonaActual $ambito,
        private readonly BancoImportRepository $filas,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsegurarPlanPersonal $plan,
    ) {
    }

    /**
     * @param list<list<string>>|null $filas Excel ya tabulado (CaixaBank).
     * @return array{nuevos:int, repetidos:int, omitidos:int, pendientes:int}
     */
    public function ejecutar(string $banco, string $csv = '', ?array $filas = null): array
    {
        $banco = strtolower(trim($banco));
        $lector = CatalogoBancosCsv::lector($banco);
        if ($filas !== null) {
            if (!$lector instanceof LectorCsvCaixaBank) {
                throw new InvalidArgumentException(_("Este banco solo admite CSV"));
            }
            $lineas = $lector->leerFilas($filas);
        } else {
            $lineas = $lector->leer($csv);
        }
        $ctx = $this->ambito->ejecutar();
        $this->plan->ejecutar($ctx->centroId, $ctx->personaId);
        $tesoreria = $this->cuentas->tesoreriaDePersona($ctx->centroId, $ctx->personaId, 'X', 'BANCO');
        if ($tesoreria === null || $tesoreria->id === null) {
            throw new InvalidArgumentException(_("No hay cuenta BANCO personal"));
        }

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
                    $asientoId = $this->asentar($ctx->centroId, $ctx->personaId, (int) $tesoreria->id, $linea);
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

    private function asentar(int $centroId, int $personaId, int $tesoreriaId, LineaExtractoBanco $linea): int
    {
        $fecha = new DateTimeImmutable($linea->fecha);
        $ejercicio = $this->ejercicios->deCentroEnFecha($centroId, $fecha);
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new InvalidArgumentException(sprintf(_("No hay ejercicio que cubra %s"), $linea->fecha));
        }
        if ($ejercicio->estado === 'cerrado') {
            throw new InvalidArgumentException(sprintf(_("El ejercicio de %s está cerrado"), $linea->fecha));
        }
        $categoria = $this->plan->pendienteDe($centroId, $personaId, $linea->sentido());
        if ($categoria->id === null) {
            throw new InvalidArgumentException(_("No hay cuenta pendiente"));
        }
        $asiento = ConstructorAsientoPersonal::movimiento(
            $ejercicio->id,
            $personaId,
            $fecha,
            $linea->concepto !== '' ? $linea->concepto : null,
            $linea->sentido(),
            $categoria->id,
            $tesoreriaId,
            $linea->centsAbs(),
            $categoria->codigo,
            $fecha,
            'banco',
        );
        $guardado = $this->asientos->guardar($asiento);
        if ($guardado->id === null) {
            throw new InvalidArgumentException(_("No se pudo guardar el movimiento"));
        }

        return $guardado->id;
    }
}
