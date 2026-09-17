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
use src\conceptos\domain\services\CatalogoConceptos;
use src\personal\domain\services\ConstructorAsientoPersonal;
use src\personal\domain\services\ResolverCategoriaPlantillaPersonal;
use src\personal\domain\value_objects\ContextoPersonal;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class RegistrarMovimientoPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly CuentaRepository $cuentas,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsientoRepository $asientos,
        private readonly PersonaRepository $personas,
        private readonly ResolverCategoriaPlantillaPersonal $categoriaPlantilla,
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
            throw new InvalidArgumentException(_("La cantidad debe ser positiva"));
        }
        $cents = $importe->toCents();
        $glosa = trim((string) ($datos['nota'] ?? $datos['glosa'] ?? ''));
        $glosa = $glosa === '' ? null : $glosa;

        if ($sentido === 'traspaso') {
            $origen = $this->tesoreria($ctx->centroId, $ctx->personaId, (string) ($datos['tesoreria_origen'] ?? ''));
            $destino = $this->tesoreria($ctx->centroId, $ctx->personaId, (string) ($datos['tesoreria_destino'] ?? ''));
            if ($fechaImputacion->format('Y-m-d') !== $fechaOperacion->format('Y-m-d')) {
                throw new InvalidArgumentException(_("Un traspaso caja/banco no admite fecha de imputación distinta"));
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

        $plantillaId = (int) ($datos['plantilla_id'] ?? 0);
        if ($plantillaId > 0) {
            if ($sentido !== 'gasto') {
                throw new InvalidArgumentException(_("Las plantillas del centro solo aplican a gastos"));
            }
            $categoria = $this->categoriaPlantilla->ejecutar($ctx->centroId, $ctx->personaId, $plantillaId);
            $gastoGenerales = false;
            $conceptoGenerales = null;
        } else {
            $plantillaId = null;
            $categoria = $this->categoria($ctx, (int) ($datos['cuenta_id'] ?? 0));
            [$gastoGenerales, $conceptoGenerales] = $this->resolverGenerales($ctx, $sentido, $datos);
        }
        $tesoreria = $this->tesoreria($ctx->centroId, $ctx->personaId, (string) ($datos['tesoreria'] ?? 'CAJA'));
        $ejercicioImp = $this->ejercicios->deCentroEnFecha($ctx->centroId, $fechaImputacion);
        $ejercicioOp = $this->ejercicios->deCentroEnFecha($ctx->centroId, $fechaOperacion);
        if ($ejercicioImp === null || $ejercicioImp->id === null) {
            throw new InvalidArgumentException(_("No hay ejercicio que cubra la fecha de imputación"));
        }
        if ($ejercicioOp === null || $ejercicioOp->id === null) {
            throw new InvalidArgumentException(_("No hay ejercicio que cubra la fecha de operación"));
        }
        if ($ejercicioOp->estado === 'cerrado') {
            throw new InvalidArgumentException(_("El ejercicio de la fecha de operación está cerrado"));
        }

        $asiento = $this->conPlantilla(ConstructorAsientoPersonal::movimiento(
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
            'manual',
            $gastoGenerales,
            $conceptoGenerales,
        ), $plantillaId);

        if ($fechaImputacion->format('Y-m-d') === $fechaOperacion->format('Y-m-d')) {
            return [$this->asientos->guardar($asiento)];
        }

        $puente = $this->cuentas->buscar($ctx->centroId, $ctx->personaId, 'X', 'PUENTE.PERIODIFICACION');
        if ($puente === null || $puente->id === null) {
            throw new InvalidArgumentException(_("Falta la cuenta de periodificación personal"));
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

        throw new InvalidArgumentException(_("Categoría no válida"));
    }

    private function tesoreria(int $centroId, int $personaId, string $maestro): Cuenta
    {
        $maestro = strtoupper(trim($maestro));
        if (!in_array($maestro, ['CAJA', 'BANCO'], true)) {
            throw new InvalidArgumentException(_("Tesorería: CAJA o BANCO"));
        }
        $cuenta = $this->cuentas->tesoreriaDePersona($centroId, $personaId, 'X', $maestro);
        if ($cuenta === null || $cuenta->id === null) {
            throw new InvalidArgumentException(sprintf(_("No hay cuenta de tesorería personal %s"), $maestro));
        }

        return $cuenta;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{0:bool,1:?string}
     */
    private function resolverGenerales(ContextoPersonal $ctx, string $sentido, array $datos): array
    {
        if ($sentido !== 'gasto') {
            return [false, null];
        }
        $flag = !empty($datos['gasto_generales']);
        if (!$flag) {
            return [false, null];
        }
        $concepto = trim((string) ($datos['concepto_generales'] ?? ''));
        if ($concepto === '') {
            throw new InvalidArgumentException(_("Indique el concepto de generales (p. ej. 204 Gas)"));
        }
        $valido = false;
        foreach (CatalogoConceptos::todos() as $c) {
            if ($c['cuenta'] === 'G' && $c['naturaleza'] === 'gasto' && $c['codigo'] === $concepto) {
                $valido = true;
                break;
            }
        }
        if (!$valido) {
            throw new InvalidArgumentException(_("Concepto de generales no válido"));
        }
        return [true, $concepto];
    }

    private function conPlantilla(Asiento $asiento, ?int $plantillaId): Asiento
    {
        if ($plantillaId === null) {
            return $asiento;
        }

        return new Asiento(
            $asiento->id,
            $asiento->ejercicioId,
            $asiento->libro,
            $asiento->numero,
            $asiento->fecha,
            $asiento->glosa,
            $asiento->tipo,
            $asiento->origen,
            $asiento->personaId,
            $asiento->movimientos,
            $asiento->conceptoCodigo,
            $asiento->asientoParId,
            $asiento->fechaOperacion(),
            $asiento->remesaId,
            $asiento->gastoGenerales,
            $asiento->conceptoGenerales,
            $plantillaId,
        );
    }

    private function parseFecha(string $raw): DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new InvalidArgumentException(_("Falta la fecha"));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return new DateTimeImmutable($raw);
        }
        $dt = DateTimeImmutable::createFromFormat('!d/m/Y', $raw);
        if ($dt === false) {
            throw new InvalidArgumentException(_("Formato de fecha incorrecto"));
        }

        return $dt;
    }
}
