<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use PDO;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\personal\domain\contracts\BancoImportRepository;
use src\personal\domain\services\ConstructorAsientoPersonal;
use src\shared\domain\value_objects\Dinero;
use Throwable;

final class DesdoblarMovimientoPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly CuentaRepository $cuentas,
        private readonly AsientoRepository $asientos,
        private readonly BancoImportRepository $bancoImport,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<Asiento>
     */
    public function ejecutar(int $id, array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $asiento = $this->asientos->porId($id);
        if ($asiento === null || $asiento->libro !== 'X' || $asiento->personaId !== $ctx->personaId) {
            throw new InvalidArgumentException('Movimiento no encontrado');
        }
        if ($asiento->tipo !== 'normal') {
            throw new InvalidArgumentException('Solo se desdoblan ingresos o gastos');
        }
        if ($asiento->origen === 'remesa' || $asiento->remesaId !== null) {
            throw new InvalidArgumentException('Un movimiento de remesa no se desdobla');
        }
        if ($asiento->asientoParId !== null) {
            throw new InvalidArgumentException('Un movimiento periodificado no se desdobla desde aquí');
        }

        $cuentasMap = [];
        foreach ($this->cuentas->listarDePersona($ctx->centroId, $ctx->personaId, 'X') as $c) {
            if ($c->id !== null) {
                $cuentasMap[$c->id] = $c;
            }
        }
        $meta = $this->analizar($asiento, $cuentasMap);
        if ($meta['sentido'] === 'traspaso') {
            throw new InvalidArgumentException('Un traspaso no se desdobla');
        }

        $partes = $datos['partes'] ?? null;
        if (!is_array($partes) || count($partes) !== 2) {
            throw new InvalidArgumentException('Indique dos partes con categoría e importe');
        }

        $preparadas = [];
        $suma = 0;
        foreach ($partes as $i => $parte) {
            if (!is_array($parte)) {
                throw new InvalidArgumentException('Parte ' . ($i + 1) . ' no válida');
            }
            $importe = Dinero::fromInput((string) ($parte['cantidad'] ?? ''));
            if ($importe->isNegative() || $importe->isZero()) {
                throw new InvalidArgumentException('La parte ' . ($i + 1) . ' debe ser mayor que cero');
            }
            $cents = $importe->toCents();
            $suma += $cents;
            $categoria = $this->categoria($ctx->centroId, $ctx->personaId, (int) ($parte['cuenta_id'] ?? 0));
            $nota = trim((string) ($parte['nota'] ?? ''));
            $preparadas[] = [
                'categoria' => $categoria,
                'cents' => $cents,
                'glosa' => $nota !== '' ? $nota : $asiento->glosa,
            ];
        }
        if ($suma !== $meta['cents']) {
            throw new InvalidArgumentException(
                'Las dos partes deben sumar ' . Dinero::fromCents($meta['cents'])->formatEs()
            );
        }

        $bancoFila = $this->bancoImport->porAsiento($id);

        $this->pdo->beginTransaction();
        try {
            $this->asientos->borrar($id);
            $guardados = [];
            foreach ($preparadas as $parte) {
                $nuevo = ConstructorAsientoPersonal::movimiento(
                    $asiento->ejercicioId,
                    $ctx->personaId,
                    $asiento->fecha,
                    $parte['glosa'],
                    $meta['sentido'],
                    (int) $parte['categoria']->id,
                    $meta['tesoreriaId'],
                    $parte['cents'],
                    $parte['categoria']->codigo,
                    $asiento->fechaOperacion(),
                    $asiento->origen,
                );
                $guardados[] = $this->asientos->guardar($nuevo);
            }
            if ($bancoFila !== null && isset($guardados[0]->id)) {
                $this->bancoImport->guardar(
                    $ctx->personaId,
                    $bancoFila['banco'],
                    $bancoFila['huella'],
                    $guardados[0]->id,
                    $bancoFila['fecha'],
                    $bancoFila['importe'],
                    $bancoFila['concepto'],
                );
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $guardados;
    }

    /**
     * @param array<int, Cuenta> $cuentasMap
     * @return array{sentido:string, tesoreriaId:int, cents:int}
     */
    private function analizar(Asiento $asiento, array $cuentasMap): array
    {
        if ($asiento->tipo === 'traspaso') {
            return ['sentido' => 'traspaso', 'tesoreriaId' => 0, 'cents' => 0];
        }
        $tesoreriaId = 0;
        $cents = 0;
        foreach ($asiento->movimientos as $mov) {
            $cuenta = $cuentasMap[$mov->cuentaId] ?? null;
            if ($cuenta?->tipo !== 'tesoreria') {
                continue;
            }
            $tesoreriaId = $mov->cuentaId;
            $cents = abs($mov->debeCents - $mov->haberCents);
            break;
        }
        if ($tesoreriaId <= 0 || $cents <= 0) {
            throw new InvalidArgumentException('No se pudo analizar el movimiento');
        }
        $sentido = 'gasto';
        foreach ($asiento->movimientos as $mov) {
            $cuenta = $cuentasMap[$mov->cuentaId] ?? null;
            if ($cuenta?->tipo === 'tesoreria') {
                $sentido = ($mov->debeCents - $mov->haberCents) >= 0 ? 'ingreso' : 'gasto';
                break;
            }
        }

        return ['sentido' => $sentido, 'tesoreriaId' => $tesoreriaId, 'cents' => $cents];
    }

    private function categoria(int $centroId, int $personaId, int $cuentaId): Cuenta
    {
        foreach ($this->cuentas->listarDePersona($centroId, $personaId, 'X') as $c) {
            if ($c->id === $cuentaId && $c->imputable && in_array($c->tipo, ['ingreso', 'gasto'], true)) {
                return $c;
            }
        }

        throw new InvalidArgumentException('Categoría no válida');
    }
}
