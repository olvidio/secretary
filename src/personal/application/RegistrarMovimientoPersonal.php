<?php

declare(strict_types=1);

namespace src\personal\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\services\ConstructorAsientoPeriodificado;
use src\personal\domain\services\ConstructorAsientoPersonal;
use src\personal\domain\value_objects\ContextoPersonal;
use src\shared\domain\value_objects\Dinero;

final class RegistrarMovimientoPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly CuentaRepository $cuentas,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsientoRepository $asientos,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<Asiento>
     */
    public function ejecutar(array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $sentido = strtolower(trim((string) ($datos['sentido'] ?? '')));
        $fechaOperacion = $this->parseFecha((string) ($datos['fecha'] ?? ''));
        $impRaw = trim((string) ($datos['fecha_imputacion'] ?? ''));
        $fechaImputacion = $impRaw === '' ? $fechaOperacion : $this->parseFecha($impRaw);
        $importe = Dinero::fromInput((string) ($datos['cantidad'] ?? ''));
        if ($importe->isNegative() || $importe->isZero()) {
            throw new InvalidArgumentException('La cantidad debe ser positiva');
        }
        $cents = $importe->toCents();
        $glosa = trim((string) ($datos['nota'] ?? $datos['glosa'] ?? ''));
        $glosa = $glosa === '' ? null : $glosa;

        if ($sentido === 'traspaso') {
            $origen = $this->tesoreria($ctx->centroId, $ctx->personaId, (string) ($datos['tesoreria_origen'] ?? ''));
            $destino = $this->tesoreria($ctx->centroId, $ctx->personaId, (string) ($datos['tesoreria_destino'] ?? ''));
            if ($fechaImputacion->format('Y-m-d') !== $fechaOperacion->format('Y-m-d')) {
                throw new InvalidArgumentException('Un traspaso caja/banco no admite fecha de imputación distinta');
            }
            $asiento = ConstructorAsientoPersonal::traspaso(
                $ctx->ejercicioId,
                $ctx->personaId,
                $fechaOperacion,
                $glosa,
                (int) $origen->id,
                (int) $destino->id,
                $cents,
            );

            return [$this->asientos->guardar($asiento)];
        }

        $categoria = $this->categoria($ctx, (int) ($datos['cuenta_id'] ?? 0));
        $tesoreria = $this->tesoreria($ctx->centroId, $ctx->personaId, (string) ($datos['tesoreria'] ?? 'CAJA'));
        $ejercicioImp = $this->ejercicios->deCentroEnFecha($ctx->centroId, $fechaImputacion);
        $ejercicioOp = $this->ejercicios->deCentroEnFecha($ctx->centroId, $fechaOperacion);
        if ($ejercicioImp === null || $ejercicioImp->id === null) {
            throw new InvalidArgumentException('No hay ejercicio que cubra la fecha de imputación');
        }
        if ($ejercicioOp === null || $ejercicioOp->id === null) {
            throw new InvalidArgumentException('No hay ejercicio que cubra la fecha de operación');
        }
        if ($ejercicioOp->estado === 'cerrado') {
            throw new InvalidArgumentException('El ejercicio de la fecha de operación está cerrado');
        }

        $asiento = ConstructorAsientoPersonal::movimiento(
            $ejercicioImp->id,
            $ctx->personaId,
            $fechaImputacion,
            $glosa,
            $sentido,
            (int) $categoria->id,
            (int) $tesoreria->id,
            $cents,
            $categoria->codigo,
            $fechaOperacion,
        );

        if ($fechaImputacion->format('Y-m-d') === $fechaOperacion->format('Y-m-d')) {
            return [$this->asientos->guardar($asiento)];
        }

        $puente = $this->cuentas->buscar($ctx->centroId, $ctx->personaId, 'X', 'PUENTE.PERIODIFICACION');
        if ($puente === null || $puente->id === null) {
            throw new InvalidArgumentException('Falta la cuenta de periodificación personal');
        }
        $par = ConstructorAsientoPeriodificado::partir(
            $asiento,
            (int) $tesoreria->id,
            $puente->id,
            $fechaImputacion,
            $fechaOperacion,
            $ejercicioImp->id,
            $ejercicioOp->id,
        );
        $permiteCerrado = $ejercicioImp->estado === 'cerrado' && $ejercicioImp->id !== $ejercicioOp->id;

        return $this->asientos->guardarEnlazados($par['imputacion'], $par['tesoreria'], $permiteCerrado);
    }

    private function categoria(ContextoPersonal $ctx, int $cuentaId): Cuenta
    {
        foreach ($this->cuentas->listarDePersona($ctx->centroId, $ctx->personaId, 'X') as $c) {
            if ($c->id === $cuentaId && $c->imputable && in_array($c->tipo, ['ingreso', 'gasto'], true)) {
                return $c;
            }
        }

        throw new InvalidArgumentException('Categoría no válida');
    }

    private function tesoreria(int $centroId, int $personaId, string $maestro): Cuenta
    {
        $maestro = strtoupper(trim($maestro));
        if (!in_array($maestro, ['CAJA', 'BANCO'], true)) {
            throw new InvalidArgumentException('Tesorería: CAJA o BANCO');
        }
        $cuenta = $this->cuentas->tesoreriaDePersona($centroId, $personaId, 'X', $maestro);
        if ($cuenta === null || $cuenta->id === null) {
            throw new InvalidArgumentException('No hay cuenta de tesorería personal ' . $maestro);
        }

        return $cuenta;
    }

    private function parseFecha(string $raw): DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new InvalidArgumentException('Falta la fecha');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return new DateTimeImmutable($raw);
        }
        $dt = DateTimeImmutable::createFromFormat('!d/m/Y', $raw);
        if ($dt === false) {
            throw new InvalidArgumentException('Formato de fecha incorrecto');
        }

        return $dt;
    }
}
