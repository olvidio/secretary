<?php

declare(strict_types=1);

namespace src\apuntes\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;
use src\apuntes\domain\entity\Apunte;
use src\asientos\domain\value_objects\FilaApunteExcel;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\services\ConstructorAsientoPeriodificado;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\cierre\application\GenerarApertura;
use src\conceptos\domain\contracts\ConceptoRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class CrearApunte
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly ConceptoRepository $conceptos,
        private readonly PersonaRepository $personas,
        private readonly ConfiguracionRepository $config,
        private readonly CuentaRepository $cuentas,
        private readonly CuentaFisicaRepository $fisicas,
        private readonly TraductorApuntesAAsientos $traductor,
        private readonly ProyectorAsientoAFilaExcel $proyector,
        private readonly ResolverAmbitoActual $ambito,
        private readonly EjercicioRepository $ejercicios,
        private readonly ?GenerarApertura $generarApertura = null,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<FilaApunteExcel>
     */
    public function ejecutar(array $datos): array
    {
        $fechaOperacion = $this->parseFecha((string) ($datos['fecha'] ?? ''));
        $fechaImputacionRaw = trim((string) ($datos['fecha_imputacion'] ?? ''));
        $fechaImputacion = $fechaImputacionRaw === '' ? $fechaOperacion : $this->parseFecha($fechaImputacionRaw);
        $cuenta = strtoupper(trim((string) ($datos['cuenta'] ?? '')));
        $origen = strtoupper(trim((string) ($datos['origen'] ?? '')));
        if (!in_array($cuenta, ['P', 'G'], true)) {
            throw new InvalidArgumentException('Cuenta P o G');
        }
        if (!in_array($origen, ['A', 'B', 'C'], true)) {
            throw new InvalidArgumentException('Origen A, B o C');
        }
        $conceptoCodigo = trim((string) ($datos['concepto_codigo'] ?? $datos['concepto'] ?? ''));
        $concepto = $this->conceptos->buscar($cuenta, $conceptoCodigo);
        if ($concepto === null) {
            throw new InvalidArgumentException('Concepto no válido para ' . $cuenta);
        }
        $iniciales = trim((string) ($datos['iniciales'] ?? ''));
        if ($iniciales === '') {
            $iniciales = '';
        }
        if ($cuenta === 'P' && $iniciales === '' && $conceptoCodigo !== '32') {
            throw new InvalidArgumentException('Las iniciales son obligatorias en P');
        }
        if ($iniciales !== '' && $this->personas->porIniciales($iniciales) === null) {
            throw new InvalidArgumentException('Iniciales no reconocidas');
        }
        $obsRaw = (string) ($datos['observaciones'] ?? '');
        $obs = $obsRaw === '' ? null : $obsRaw;
        $cantidad = Dinero::fromInput((string) ($datos['cantidad'] ?? ''));
        if ($cantidad->isNegative() || $cantidad->isZero()) {
            throw new InvalidArgumentException('La cantidad debe ser positiva');
        }
        $esCierre = !empty($datos['es_cierre']);
        $fechasDistintas = $fechaImputacion->format('Y-m-d') !== $fechaOperacion->format('Y-m-d');
        if ($fechasDistintas) {
            if ($origen === 'A') {
                throw new InvalidArgumentException(
                    'La fecha de imputación distinta solo aplica a caja o banco (origen B o C)'
                );
            }
            if (in_array($conceptoCodigo, ['41', '42'], true)) {
                throw new InvalidArgumentException('Un traspaso caja/banco no admite fecha de imputación distinta');
            }
            if ($esCierre) {
                throw new InvalidArgumentException('El asiento de cierre de mes no admite fecha de imputación distinta');
            }
        }

        $this->config->get();
        $contexto = $this->ambito->ejecutar();
        $centroId = $contexto->centroId;
        [$ejercicioImputacion, $ejercicioOperacion, $permiteCerradoImputacion] = $this->resolverEjercicios(
            $centroId,
            $fechaImputacion,
            $fechaOperacion,
        );
        if ($ejercicioOperacion->id === null || $ejercicioImputacion->id === null) {
            throw new InvalidArgumentException('Ejercicio sin identificador');
        }
        $ejercicioId = $ejercicioOperacion->id;
        $cuentaFisicaId = isset($datos['cuenta_fisica_id']) && $datos['cuenta_fisica_id'] !== ''
            ? (int) $datos['cuenta_fisica_id']
            : null;

        $apunte = new Apunte(
            null,
            $fechaOperacion,
            $cuenta,
            $origen,
            $iniciales !== '' ? $iniciales : null,
            $conceptoCodigo,
            $obs,
            $cantidad,
            $esCierre,
            null,
        );

        $personasPorIniciales = [];
        foreach ($this->personas->listarDeCentro($centroId) as $persona) {
            $personasPorIniciales[strtolower($persona->iniciales)] = $persona;
        }

        $origenAsiento = $esCierre ? 'cierre' : 'manual';
        $origenApunte = $origen;
        $conceptoApunte = $conceptoCodigo;
        $resultado = $this->traductor->traducir(
            $ejercicioId,
            [$apunte],
            static function (string $ini) use ($personasPorIniciales) {
                return $personasPorIniciales[strtolower($ini)] ?? null;
            },
            fn (string $libro, string $codigo): \src\ambito\domain\entity\Cuenta => $this->resolverConcepto($centroId, $libro, $codigo),
            fn (string $libro, string $codigoMaestro): \src\ambito\domain\entity\Cuenta => $this->resolverTesoreriaParaApunte(
                $centroId,
                $libro,
                $codigoMaestro,
                $origenApunte,
                $conceptoApunte,
                $cuentaFisicaId,
            ),
            fn (int $personaId): \src\ambito\domain\entity\Cuenta => $this->resolverPersonal($centroId, $personaId),
            fn (): \src\ambito\domain\entity\Cuenta => $this->resolverDeudores($centroId),
            $origenAsiento,
        );

        if ($resultado['asientos'] === []) {
            throw new InvalidArgumentException('No se pudo traducir el apunte a asiento');
        }

        $asiento = $resultado['asientos'][0]->withFechaOperacion($fechaOperacion);
        $mapaCuentas = $this->mapaCuentas($centroId);
        $mapaPersonas = $this->mapaPersonas($centroId);

        if (!$fechasDistintas) {
            $asientoGuardado = $this->asientos->guardar($asiento);

            return [$this->proyector->proyectar($asientoGuardado, $mapaCuentas, $mapaPersonas)];
        }

        $tesoreriaCuentaId = $this->idCuentaTesoreria($asiento, $mapaCuentas);
        $puente = $this->cuentas->puentePeriodificacion($centroId, $asiento->libro);
        if ($puente === null || $puente->id === null) {
            throw new InvalidArgumentException('Falta la cuenta PUENTE.PERIODIFICACION del libro ' . $asiento->libro);
        }

        $par = ConstructorAsientoPeriodificado::partir(
            $asiento,
            $tesoreriaCuentaId,
            $puente->id,
            $fechaImputacion,
            $fechaOperacion,
            $ejercicioImputacion->id,
            $ejercicioOperacion->id,
        );
        [$imputacion, $tesoreria] = $this->asientos->guardarEnlazados(
            $par['imputacion'],
            $par['tesoreria'],
            $permiteCerradoImputacion,
        );
        if ($permiteCerradoImputacion && $this->generarApertura !== null) {
            $this->generarApertura->ejecutar($ejercicioOperacion->id);
        }

        return [$this->proyector->proyectarPar($imputacion, $tesoreria, $mapaCuentas, $mapaPersonas)];
    }

    /**
     * @return array{0: Ejercicio, 1: Ejercicio, 2: bool}
     */
    private function resolverEjercicios(
        int $centroId,
        DateTimeImmutable $fechaImputacion,
        DateTimeImmutable $fechaOperacion,
    ): array {
        $imputacion = $this->ejercicios->deCentroEnFecha($centroId, $fechaImputacion);
        if ($imputacion === null || $imputacion->id === null) {
            throw new InvalidArgumentException(
                'No hay ejercicio que cubra la fecha de imputación ' . $fechaImputacion->format('d/m/Y')
            );
        }

        $operacion = $this->ejercicios->deCentroEnFecha($centroId, $fechaOperacion);
        if ($operacion === null) {
            if ($imputacion->estado === 'abierto' && $fechaOperacion > $imputacion->fechaFin) {
                $operacion = $imputacion;
            } else {
                throw new InvalidArgumentException(
                    'No hay ejercicio que cubra la fecha de operación ' . $fechaOperacion->format('d/m/Y')
                    . '. Cree el ejercicio siguiente o deje la imputación en el mismo período.'
                );
            }
        }
        if ($operacion->id === null) {
            throw new InvalidArgumentException('Ejercicio de operación sin identificador');
        }
        if ($operacion->estado === 'cerrado') {
            throw new InvalidArgumentException(
                'El ejercicio de la fecha de operación está cerrado; reábralo o use el ejercicio abierto'
            );
        }

        $permiteCerradoImputacion = false;
        if ($imputacion->estado === 'cerrado') {
            if ($imputacion->id === $operacion->id) {
                throw new InvalidArgumentException(
                    'No se pueden registrar asientos en un ejercicio cerrado; reábralo o use el ejercicio abierto'
                );
            }
            $permiteCerradoImputacion = true;
        }

        return [$imputacion, $operacion, $permiteCerradoImputacion];
    }

    /**
     * @param array<int, \src\ambito\domain\entity\Cuenta> $mapaCuentas
     */
    private function idCuentaTesoreria(Asiento $asiento, array $mapaCuentas): int
    {
        $tesoreriaId = null;
        foreach ($asiento->movimientos as $mov) {
            $cuenta = $mapaCuentas[$mov->cuentaId] ?? null;
            if ($cuenta !== null && $cuenta->tipo === 'tesoreria') {
                if ($tesoreriaId !== null) {
                    throw new InvalidArgumentException('El asiento tiene más de una cuenta de tesorería; no se periodifica');
                }
                $tesoreriaId = $cuenta->id;
            }
        }
        if ($tesoreriaId === null) {
            throw new InvalidArgumentException('No hay tesorería que periodificar en este asiento');
        }

        return $tesoreriaId;
    }

    private function resolverConcepto(int $centroId, string $libro, string $codigo): \src\ambito\domain\entity\Cuenta
    {
        $cuenta = $this->cuentas->buscar($centroId, null, $libro, $codigo);
        if ($cuenta === null) {
            throw new InvalidArgumentException(sprintf('Cuenta de concepto no encontrada: %s/%s', $libro, $codigo));
        }

        return $cuenta;
    }

    private function resolverTesoreria(int $centroId, string $libro, string $codigoMaestro): \src\ambito\domain\entity\Cuenta
    {
        $cuenta = $this->cuentas->tesoreria($centroId, $libro, $codigoMaestro);
        if ($cuenta === null) {
            throw new InvalidArgumentException(sprintf('Cuenta de tesorería no encontrada: %s/%s', $libro, $codigoMaestro));
        }

        return $cuenta;
    }

    private function resolverTesoreriaParaApunte(
        int $centroId,
        string $libro,
        string $codigoMaestro,
        string $origenApunte,
        string $conceptoApunte,
        ?int $cuentaFisicaId,
    ): \src\ambito\domain\entity\Cuenta {
        if (in_array($conceptoApunte, ['41', '42'], true)) {
            return $this->resolverTesoreria($centroId, $libro, $codigoMaestro);
        }
        if ($origenApunte === 'A') {
            return $this->resolverTesoreria($centroId, $libro, $codigoMaestro);
        }

        $tipoFisica = match ($origenApunte) {
            'C' => 'caja',
            'B' => 'banco',
            default => throw new InvalidArgumentException('Origen no válido para tesorería: ' . $origenApunte),
        };
        $maestroEsperado = $tipoFisica === 'caja' ? 'CAJA' : 'BANCO';
        if ($codigoMaestro !== $maestroEsperado) {
            return $this->resolverTesoreria($centroId, $libro, $codigoMaestro);
        }

        if ($cuentaFisicaId !== null) {
            $fisica = $this->fisicas->porId($cuentaFisicaId);
            if ($fisica === null || $fisica->centroId !== $centroId || !$fisica->activo) {
                throw new InvalidArgumentException('Cuenta física no válida');
            }
            if ($fisica->tipo !== $tipoFisica) {
                throw new InvalidArgumentException(
                    sprintf('La cuenta física indicada no es de tipo %s', $tipoFisica)
                );
            }
            $cuenta = $this->cuentas->tesoreriaDeFisica($centroId, $libro, $cuentaFisicaId);
            if ($cuenta === null) {
                throw new InvalidArgumentException('Cuenta de mayor de tesorería no encontrada para la física indicada');
            }

            return $cuenta;
        }

        $activas = $this->fisicas->listarActivasDeCentro($centroId, $tipoFisica);
        if (count($activas) > 1) {
            $etiqueta = $tipoFisica === 'caja' ? 'caja' : 'banco';
            throw new InvalidArgumentException(
                sprintf('Hay varias %ss activas: indique cuenta_fisica_id', $etiqueta)
            );
        }
        if (count($activas) === 1 && $activas[0]->id !== null) {
            $cuenta = $this->cuentas->tesoreriaDeFisica($centroId, $libro, $activas[0]->id);
            if ($cuenta !== null) {
                return $cuenta;
            }
        }

        return $this->resolverTesoreria($centroId, $libro, $codigoMaestro);
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

    /** @return array<int, \src\ambito\domain\entity\Cuenta> */
    private function mapaCuentas(int $centroId): array
    {
        $mapa = [];
        foreach ($this->cuentas->listarDeCentro($centroId) as $cuenta) {
            if ($cuenta->id !== null) {
                $mapa[$cuenta->id] = $cuenta;
            }
        }

        return $mapa;
    }

    /** @return array<int, \src\personas\domain\entity\Persona> */
    private function mapaPersonas(int $centroId): array
    {
        $mapa = [];
        foreach ($this->personas->listarDeCentro($centroId) as $persona) {
            if ($persona->id !== null) {
                $mapa[$persona->id] = $persona;
            }
        }

        return $mapa;
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
