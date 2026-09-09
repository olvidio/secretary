<?php

declare(strict_types=1);

namespace src\asientos\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\value_objects\ContextoActual;
use src\apuntes\domain\contracts\ApunteRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\personas\domain\contracts\PersonaRepository;

/**
 * Conversión apuntes → asientos (Fase 3). Tras la Fase 5 la importación ya
 * genera asientos de forma idempotente; si el ejercicio tiene asientos, no-op.
 */
final class ConvertirApuntesAAsientos
{
    public function __construct(
        private readonly ContextoActual $contexto,
        private readonly ApunteRepository $apuntes,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly PersonaRepository $personas,
        private readonly TraductorApuntesAAsientos $traductor,
    ) {
    }

    /**
     * @return array{asientos: int, omitidos_concepto_9: int, traspasos_fusionados: int}
     */
    public function ejecutar(string $origenAsiento = 'import', ?int $centroId = null, ?int $ejercicioId = null): array
    {
        $centroId ??= $this->contexto->centroId;
        $ejercicioId ??= $this->contexto->ejercicioId;

        $yaHay = $this->asientos->listarPorEjercicio($ejercicioId);
        if ($yaHay !== []) {
            return [
                'asientos' => count($yaHay),
                'omitidos_concepto_9' => 0,
                'traspasos_fusionados' => 0,
            ];
        }

        $listaApuntes = $this->apuntes->listar();

        $personasPorIniciales = [];
        foreach ($this->personas->listar() as $persona) {
            $personasPorIniciales[strtolower($persona->iniciales)] = $persona;
        }

        $resultado = $this->traductor->traducir(
            $ejercicioId,
            $listaApuntes,
            static function (string $iniciales) use ($personasPorIniciales) {
                return $personasPorIniciales[strtolower($iniciales)] ?? null;
            },
            fn (string $libro, string $codigo): \src\ambito\domain\entity\Cuenta => $this->resolverConcepto($centroId, $libro, $codigo),
            fn (string $libro, string $codigoMaestro): \src\ambito\domain\entity\Cuenta => $this->resolverTesoreria($centroId, $libro, $codigoMaestro),
            fn (int $personaId): \src\ambito\domain\entity\Cuenta => $this->resolverPersonal($centroId, $personaId),
            fn (): \src\ambito\domain\entity\Cuenta => $this->resolverDeudores($centroId),
            $origenAsiento,
        );

        $guardados = 0;
        foreach ($resultado['asientos'] as $asiento) {
            $this->asientos->guardar($asiento);
            ++$guardados;
        }

        return [
            'asientos' => $guardados,
            'omitidos_concepto_9' => $resultado['omitidos_concepto_9'],
            'traspasos_fusionados' => $resultado['traspasos_fusionados'],
        ];
    }

    private function resolverConcepto(int $centroId, string $libro, string $codigo): \src\ambito\domain\entity\Cuenta
    {
        $cuenta = $this->cuentas->buscar($centroId, null, $libro, $codigo);
        if ($cuenta === null) {
            throw new InvalidArgumentException(
                sprintf('Cuenta de concepto no encontrada: %s/%s', $libro, $codigo)
            );
        }

        return $cuenta;
    }

    private function resolverTesoreria(int $centroId, string $libro, string $codigoMaestro): \src\ambito\domain\entity\Cuenta
    {
        $cuenta = $this->cuentas->tesoreria($centroId, $libro, $codigoMaestro);
        if ($cuenta === null) {
            throw new InvalidArgumentException(
                sprintf('Cuenta de tesorería no encontrada: %s/%s', $libro, $codigoMaestro)
            );
        }

        return $cuenta;
    }

    private function resolverPersonal(int $centroId, int $personaId): \src\ambito\domain\entity\Cuenta
    {
        $cuenta = $this->cuentas->personalDe($centroId, $personaId);
        if ($cuenta === null) {
            throw new InvalidArgumentException('Cuenta personal no encontrada para persona ' . $personaId);
        }

        return $cuenta;
    }

    private function resolverDeudores(int $centroId): \src\ambito\domain\entity\Cuenta
    {
        $cuenta = $this->cuentas->deudoresVivienda($centroId);
        if ($cuenta === null) {
            throw new InvalidArgumentException('Cuenta DEUDORES.VIV no encontrada');
        }

        return $cuenta;
    }
}
