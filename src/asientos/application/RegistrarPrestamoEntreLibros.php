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

final class RegistrarPrestamoEntreLibros
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly CuentaFisicaRepository $fisicas,
        private readonly ConfiguracionRepository $config,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{origen: Asiento, destino: Asiento}
     */
    public function ejecutar(array $datos): array
    {
        $fisicaId = (int) ($datos['cuenta_fisica_id'] ?? 0);
        $libroOrigen = strtoupper(trim((string) ($datos['libro_origen'] ?? '')));
        $libroDestino = strtoupper(trim((string) ($datos['libro_destino'] ?? '')));
        if ($fisicaId <= 0) {
            throw new InvalidArgumentException('Indique la cuenta física');
        }
        if (!in_array($libroOrigen, ['P', 'G'], true) || !in_array($libroDestino, ['P', 'G'], true)) {
            throw new InvalidArgumentException('Libros P o G');
        }
        if ($libroOrigen === $libroDestino) {
            throw new InvalidArgumentException('Origen y destino deben ser libros distintos');
        }

        $contexto = $this->ambito->ejecutar();
        $fisica = $this->fisicas->porId($fisicaId);
        if ($fisica === null || $fisica->centroId !== $contexto->centroId || !$fisica->activo) {
            throw new InvalidArgumentException('Cuenta física no válida');
        }

        $tesoreriaOrigen = $this->cuentas->tesoreriaDeFisica($contexto->centroId, $libroOrigen, $fisicaId);
        $tesoreriaDestino = $this->cuentas->tesoreriaDeFisica($contexto->centroId, $libroDestino, $fisicaId);
        $puenteOrigen = $this->cuentas->puenteEntreLibros($contexto->centroId, $libroOrigen);
        $puenteDestino = $this->cuentas->puenteEntreLibros($contexto->centroId, $libroDestino);
        if ($tesoreriaOrigen?->id === null || $tesoreriaDestino?->id === null
            || $puenteOrigen?->id === null || $puenteDestino?->id === null) {
            throw new InvalidArgumentException('Faltan cuentas de tesorería o puente entre libros');
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

        // Sale dinero del libro origen: Debe puente / Haber tesorería.
        $asientoOrigen = new Asiento(
            null,
            $contexto->ejercicioId,
            $libroOrigen,
            null,
            $fecha,
            $glosa,
            'traspaso',
            'manual',
            null,
            [
                new Movimiento(null, 1, $puenteOrigen->id, null, $cents, 0),
                new Movimiento(null, 2, $tesoreriaOrigen->id, null, 0, $cents),
            ],
        );
        // Entra en el libro destino: Debe tesorería / Haber puente.
        $asientoDestino = new Asiento(
            null,
            $contexto->ejercicioId,
            $libroDestino,
            null,
            $fecha,
            $glosa,
            'traspaso',
            'manual',
            null,
            [
                new Movimiento(null, 1, $tesoreriaDestino->id, null, $cents, 0),
                new Movimiento(null, 2, $puenteDestino->id, null, 0, $cents),
            ],
        );

        $guardadoOrigen = $this->asientos->guardar($asientoOrigen);
        $guardadoDestino = $this->asientos->guardar($asientoDestino);
        if ($guardadoOrigen->id === null || $guardadoDestino->id === null) {
            throw new InvalidArgumentException('No se pudieron guardar los asientos del préstamo');
        }
        $this->asientos->enlazar($guardadoOrigen->id, $guardadoDestino->id);

        $origenEnlazado = $this->asientos->porId($guardadoOrigen->id);
        $destinoEnlazado = $this->asientos->porId($guardadoDestino->id);
        if ($origenEnlazado === null || $destinoEnlazado === null) {
            throw new InvalidArgumentException('No se pudieron releer los asientos enlazados');
        }

        return ['origen' => $origenEnlazado, 'destino' => $destinoEnlazado];
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
