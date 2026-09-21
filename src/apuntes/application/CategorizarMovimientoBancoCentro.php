<?php

declare(strict_types=1);

namespace src\apuntes\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Cuenta;
use src\apuntes\domain\contracts\BancoCentroImportRepository;
use src\apuntes\domain\services\ContrapartidasGastoGeneral;
use src\apuntes\domain\services\ConstructorAsientoCentroBanco;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\value_objects\FilaApunteExcel;
use src\conceptos\application\ResolverConceptosCentro;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class CategorizarMovimientoBancoCentro
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly CuentaFisicaRepository $fisicas,
        private readonly EjercicioRepository $ejercicios,
        private readonly BancoCentroImportRepository $filasImport,
        private readonly CrearApuntesDeEntrada $crearApuntes,
        private readonly ResolverConceptosCentro $conceptos,
        private readonly ContrapartidasGastoGeneral $contrapartidas,
        private readonly AsegurarPendienteBancoCentro $pendientes,
        private readonly PersonaRepository $personas,
    ) {
    }

    public function asignarConceptoG(
        int $filaId,
        string $conceptoCodigo,
        ?string $iniciales = null,
        ?string $observaciones = null,
    ): void {
        $ctx = $this->ambito->ejecutar();
        $fila = $this->filaPendiente($filaId, $ctx->centroId);
        $conceptoCodigo = trim($conceptoCodigo);
        $concepto = $this->conceptos->buscar($ctx->centroId, 'G', $conceptoCodigo);
        if ($concepto === null) {
            throw new InvalidArgumentException(_('Concepto no válido para G'));
        }
        if (!in_array($concepto->naturaleza, ['ingreso', 'gasto'], true)) {
            throw new InvalidArgumentException(_('Elija un concepto de ingreso o gasto'));
        }
        $sentido = (string) $fila['sentido'];
        if ($concepto->naturaleza !== $sentido) {
            throw new InvalidArgumentException(sprintf(
                _('Ese concepto es de %s; el movimiento es un %s'),
                $concepto->naturaleza,
                $sentido,
            ));
        }

        $this->anularAsientoPrevio($fila);
        $ini = trim((string) ($iniciales ?? ''));
        $obs = $this->glosaFila($fila, $observaciones);
        $cantidad = $this->cantidadEs($fila);

        $datos = [
            'cuenta' => 'G',
            'origen' => 'B',
            'fecha' => (string) $fila['fecha'],
            'concepto_codigo' => $conceptoCodigo,
            'iniciales' => $ini,
            'observaciones' => $obs ?? '',
            'cantidad' => $cantidad,
        ];
        if ($fila['cuenta_fisica_id'] !== null) {
            $datos['cuenta_fisica_id'] = $fila['cuenta_fisica_id'];
        }
        if ($this->contrapartidas->lineas('G', 'B', $conceptoCodigo, $sentido, $ini, $obs) !== null) {
            $datos['contrapartidas'] = true;
        }
        $filasApunte = $this->crearApuntes->ejecutar($datos);
        $asientoId = $this->asientoPrincipalLibro($filasApunte, 'G');
        $this->filasImport->vincularAsiento($filaId, $asientoId, null, $conceptoCodigo);
    }

    public function asignarConceptoP(
        int $filaId,
        string $conceptoCodigo,
        string $iniciales,
        ?string $observaciones = null,
    ): void {
        $ini = trim($iniciales);
        if ($ini === '') {
            throw new InvalidArgumentException(_('Las iniciales son obligatorias en P'));
        }
        $ctx = $this->ambito->ejecutar();
        $persona = $this->personas->porInicialesDeCentro($ctx->centroId, $ini);
        if ($persona === null || $persona->id === null) {
            throw new InvalidArgumentException(_('Iniciales no reconocidas en este centro'));
        }
        $fila = $this->filaPendiente($filaId, $ctx->centroId);
        $conceptoCodigo = trim($conceptoCodigo);
        $concepto = $this->conceptos->buscar($ctx->centroId, 'P', $conceptoCodigo);
        if ($concepto === null) {
            throw new InvalidArgumentException(_('Concepto no válido para P'));
        }
        if (!in_array($concepto->naturaleza, ['ingreso', 'gasto'], true)) {
            throw new InvalidArgumentException(_('Elija un concepto de ingreso o gasto'));
        }
        $sentido = (string) $fila['sentido'];
        if ($concepto->naturaleza !== $sentido) {
            throw new InvalidArgumentException(sprintf(
                _('Ese concepto es de %s; el movimiento es un %s'),
                $concepto->naturaleza,
                $sentido,
            ));
        }

        $this->anularAsientoPrevio($fila);
        $obs = $this->glosaFila($fila, $observaciones);
        $cantidad = $this->cantidadEs($fila);

        $datos = [
            'cuenta' => 'P',
            'origen' => 'B',
            'fecha' => (string) $fila['fecha'],
            'concepto_codigo' => $conceptoCodigo,
            'iniciales' => $ini,
            'observaciones' => $obs ?? '',
            'cantidad' => $cantidad,
        ];
        if ($fila['cuenta_fisica_id'] !== null) {
            $datos['cuenta_fisica_id'] = $fila['cuenta_fisica_id'];
        }
        $filasApunte = $this->crearApuntes->ejecutar($datos);
        $asientoId = $this->asientoPrincipalLibro($filasApunte, 'P');
        $this->filasImport->vincularAsiento($filaId, $asientoId, $persona->id, $conceptoCodigo);
    }

    public function traspasoACaja(int $filaId, ?string $observaciones = null): void
    {
        $ctx = $this->ambito->ejecutar();
        $fila = $this->filaPendiente($filaId, $ctx->centroId);
        $conceptoCodigo = $fila['sentido'] === 'gasto' ? '41' : '42';
        $this->anularAsientoPrevio($fila);
        $obs = $this->glosaFila($fila, $observaciones);
        $cantidad = $this->cantidadEs($fila);

        $datos = [
            'cuenta' => 'G',
            'origen' => 'B',
            'fecha' => (string) $fila['fecha'],
            'concepto_codigo' => $conceptoCodigo,
            'observaciones' => $obs ?? '',
            'cantidad' => $cantidad,
        ];
        if ($fila['cuenta_fisica_id'] !== null) {
            $datos['cuenta_fisica_id'] = $fila['cuenta_fisica_id'];
        }
        $filasApunte = $this->crearApuntes->ejecutar($datos);
        $asientoId = $this->asientoPrincipalLibro($filasApunte, 'G');
        $this->filasImport->vincularAsiento($filaId, $asientoId, null, $conceptoCodigo);
    }

    public function otraContabilidad(int $filaId, ?string $observaciones = null): void
    {
        $ctx = $this->ambito->ejecutar();
        $fila = $this->filaPendiente($filaId, $ctx->centroId);
        $sentido = (string) $fila['sentido'];
        $otra = $this->pendientes->otraDe($ctx->centroId, $sentido);
        $glosa = $this->glosaFila($fila, $observaciones);
        $cents = $this->centsAbs($fila);
        $tesoreria = $this->resolverTesoreriaG($ctx->centroId, $fila['cuenta_fisica_id']);

        if ($fila['asiento_id'] !== null) {
            $contexto = $this->contextoAsientoG((int) $fila['asiento_id'], $ctx->centroId);
            $reconstruido = ConstructorAsientoCentroBanco::movimiento(
                $contexto['asiento']->ejercicioId,
                $contexto['asiento']->fecha,
                $glosa,
                $sentido,
                (int) $otra->id,
                $contexto['tesoreriaId'],
                $cents,
                $otra->codigo,
            );
            $this->guardarActualizado($contexto['asiento'], $reconstruido, $glosa, $otra->codigo);
            $this->filasImport->vincularAsiento($filaId, (int) $fila['asiento_id'], null, $otra->codigo);

            return;
        }

        $this->anularAsientoPrevio($fila);
        $ejercicio = $this->ejercicioDeFila($ctx->centroId, $fila);
        $asiento = ConstructorAsientoCentroBanco::movimiento(
            $ejercicio->id,
            new DateTimeImmutable((string) $fila['fecha']),
            $glosa,
            $sentido,
            (int) $otra->id,
            (int) $tesoreria->id,
            $cents,
            $otra->codigo,
        );
        $guardado = $this->asientos->guardar($asiento);
        if ($guardado->id === null) {
            throw new InvalidArgumentException(_('No se pudo guardar el movimiento'));
        }
        $this->filasImport->vincularAsiento($filaId, $guardado->id, null, $otra->codigo);
    }

    /**
     * @return array<string, mixed>
     */
    private function filaPendiente(int $filaId, int $centroId): array
    {
        $fila = $this->filasImport->porId($centroId, $filaId);
        if ($fila === null) {
            throw new InvalidArgumentException(_('Movimiento de banco no encontrado'));
        }
        if ($fila['asiento_id'] !== null && $fila['persona_id'] !== null) {
            throw new InvalidArgumentException(_('Este movimiento ya está categorizado'));
        }

        return $fila;
    }

    /**
     * @param array<string, mixed> $fila
     */
    private function anularAsientoPrevio(array $fila): void
    {
        if ($fila['asiento_id'] === null) {
            return;
        }
        $asiento = $this->asientos->porId((int) $fila['asiento_id']);
        if ($asiento !== null && $asiento->origen === 'banco') {
            $this->asientos->anular((int) $fila['asiento_id']);
        }
    }

    /**
     * @param array<string, mixed> $fila
     */
    private function glosaFila(array $fila, ?string $observaciones): ?string
    {
        if ($observaciones !== null) {
            $obs = trim($observaciones);
            if (mb_strlen($obs) > 250) {
                $obs = mb_substr($obs, 0, 250);
            }

            return $obs === '' ? null : $obs;
        }
        $concepto = trim((string) ($fila['concepto'] ?? ''));

        return $concepto === '' ? null : $concepto;
    }

    /**
     * @param array<string, mixed> $fila
     */
    private function cantidadEs(array $fila): string
    {
        return Dinero::fromCents($this->centsAbs($fila))->formatEs();
    }

    /**
     * @param array<string, mixed> $fila
     */
    private function centsAbs(array $fila): int
    {
        return abs((new Dinero((string) $fila['importe']))->toCents());
    }

    /**
     * @param list<FilaApunteExcel> $filas
     */
    private function asientoPrincipalLibro(array $filas, string $libro): int
    {
        foreach ($filas as $fila) {
            if ($fila->cuenta === $libro && $fila->origen === 'B') {
                return $fila->id;
            }
        }
        if ($filas === []) {
            throw new InvalidArgumentException(_('No se pudo crear el asiento'));
        }

        return $filas[0]->id;
    }

    private function resolverTesoreriaG(int $centroId, ?int $cuentaFisicaId): Cuenta
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

    /**
     * @param array<string, mixed> $fila
     */
    private function ejercicioDeFila(int $centroId, array $fila): \src\ambito\domain\entity\Ejercicio
    {
        $fecha = new DateTimeImmutable((string) $fila['fecha']);
        $ejercicio = $this->ejercicios->deCentroEnFecha($centroId, $fecha);
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new InvalidArgumentException(sprintf(_('No hay ejercicio que cubra %s'), $fila['fecha']));
        }
        if ($ejercicio->estado === 'cerrado') {
            throw new InvalidArgumentException(sprintf(_('El ejercicio de %s está cerrado'), $fila['fecha']));
        }

        return $ejercicio;
    }

    /**
     * @return array{
     *     asiento: Asiento,
     *     tesoreriaId: int
     * }
     */
    private function contextoAsientoG(int $asientoId, int $centroId): array
    {
        $this->pendientes->ejecutar($centroId);
        $asiento = $this->asientos->porId($asientoId);
        if (
            $asiento === null
            || $asiento->id === null
            || $asiento->libro !== 'G'
            || $asiento->origen !== 'banco'
        ) {
            throw new InvalidArgumentException(_('Movimiento de banco no encontrado'));
        }

        $cuentas = [];
        foreach ($this->cuentas->listarDeCentro($centroId) as $c) {
            if ($c->id !== null && $c->libro === 'G') {
                $cuentas[$c->id] = $c;
            }
        }

        $tesoreria = null;
        $contrapartida = null;
        foreach ($asiento->movimientos as $mov) {
            $cuenta = $cuentas[$mov->cuentaId] ?? null;
            if ($cuenta === null) {
                continue;
            }
            if ($cuenta->tipo === 'tesoreria') {
                $tesoreria = $cuenta;
            }
            if (
                AsegurarPendienteBancoCentro::esPendiente($cuenta->codigo)
                || AsegurarPendienteBancoCentro::esOtra($cuenta->codigo)
            ) {
                $contrapartida = $cuenta;
            }
        }
        if ($tesoreria === null || $tesoreria->id === null || $contrapartida === null) {
            throw new InvalidArgumentException(_('El movimiento no está pendiente de categorizar'));
        }

        return [
            'asiento' => $asiento,
            'tesoreriaId' => $tesoreria->id,
        ];
    }

    private function guardarActualizado(
        Asiento $asiento,
        Asiento $reconstruido,
        ?string $glosa,
        ?string $conceptoCodigo,
    ): void {
        $this->asientos->actualizar(new Asiento(
            $asiento->id,
            $asiento->ejercicioId,
            $asiento->libro,
            $asiento->numero,
            $asiento->fecha,
            $glosa,
            $reconstruido->tipo,
            $asiento->origen,
            $asiento->personaId,
            $reconstruido->movimientos,
            $conceptoCodigo,
            $asiento->plantillaApunteId,
            $asiento->fechaOperacion(),
        ));
    }
}
