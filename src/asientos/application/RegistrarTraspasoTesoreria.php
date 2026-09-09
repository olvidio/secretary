<?php

declare(strict_types=1);

namespace src\asientos\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\ambito\domain\contracts\CuentaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\shared\domain\value_objects\Dinero;

final class RegistrarTraspasoTesoreria
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly CuentaFisicaRepository $fisicas,
        private readonly ConfiguracionRepository $config,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): Asiento
    {
        $libro = strtoupper(trim((string) ($datos['libro'] ?? '')));
        if (!in_array($libro, ['P', 'G'], true)) {
            throw new InvalidArgumentException('Libro P o G');
        }
        $origenId = (int) ($datos['cuenta_fisica_origen_id'] ?? 0);
        $destinoId = (int) ($datos['cuenta_fisica_destino_id'] ?? 0);
        if ($origenId <= 0 || $destinoId <= 0) {
            throw new InvalidArgumentException('Indique las cuentas físicas de origen y destino');
        }
        if ($origenId === $destinoId) {
            throw new InvalidArgumentException('Origen y destino no pueden ser la misma cuenta física');
        }

        $contexto = $this->ambito->ejecutar();
        $this->validarFisicaDelCentro($contexto->centroId, $origenId);
        $this->validarFisicaDelCentro($contexto->centroId, $destinoId);

        $cuentaOrigen = $this->cuentas->tesoreriaDeFisica($contexto->centroId, $libro, $origenId);
        $cuentaDestino = $this->cuentas->tesoreriaDeFisica($contexto->centroId, $libro, $destinoId);
        if ($cuentaOrigen === null || $cuentaDestino === null) {
            throw new InvalidArgumentException('Las cuentas de mayor de tesorería no existen para ese libro');
        }
        if ($cuentaOrigen->id === null || $cuentaDestino->id === null) {
            throw new InvalidArgumentException('Cuentas de tesorería sin identificador');
        }

        $fecha = $this->parseFecha((string) ($datos['fecha'] ?? ''));
        if (!$this->config->get()->periodo()->contiene($fecha)) {
            throw new InvalidArgumentException('La fecha no corresponde al ejercicio');
        }

        $importe = Dinero::fromInput((string) ($datos['cantidad'] ?? ''));
        if ($importe->isNegative() || $importe->isZero()) {
            throw new InvalidArgumentException('La cantidad debe ser positiva');
        }
        $cents = $importe->toCents();
        $glosa = isset($datos['glosa']) && trim((string) $datos['glosa']) !== ''
            ? trim((string) $datos['glosa'])
            : null;

        $asiento = new Asiento(
            null,
            $contexto->ejercicioId,
            $libro,
            null,
            $fecha,
            $glosa,
            'traspaso',
            'manual',
            null,
            [
                new Movimiento(null, 1, $cuentaDestino->id, null, $cents, 0),
                new Movimiento(null, 2, $cuentaOrigen->id, null, 0, $cents),
            ],
        );

        return $this->asientos->guardar($asiento);
    }

    private function validarFisicaDelCentro(int $centroId, int $fisicaId): void
    {
        $fisica = $this->fisicas->porId($fisicaId);
        if ($fisica === null || $fisica->centroId !== $centroId || !$fisica->activo) {
            throw new InvalidArgumentException('Cuenta física no válida');
        }
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
