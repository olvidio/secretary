<?php

declare(strict_types=1);

namespace src\remesas\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;
use src\asientos\domain\contracts\AsientoRepository;
use src\personal\application\ResolverPeriodoPersonal;
use src\personal\application\ResolverPersonaActual;
use src\personal\domain\contracts\RemanenteRepository;
use src\personal\domain\services\PeriodoPersonal;
use src\personal\domain\value_objects\ContextoPersonal;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\entity\RemesaLinea;
use src\remesas\domain\services\AgregadorRemesaPersonal;
use src\remesas\domain\services\CalculoDisponibleRemesa;

final class ResolverMesRemesa
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly ResolverPeriodoPersonal $periodoPersonal,
        private readonly RemanenteRepository $remanentes,
        private readonly PersonaRepository $personas,
        private readonly CentroRepository $centros,
        private readonly IdentidadRepository $identidades,
        private readonly ?int $identidadId = null,
    ) {
    }

    /**
     * @return array{
     *   ctx: ContextoPersonal,
     *   ejercicio: Ejercicio,
     *   destino_persona_id: int,
     *   destino_centro_id: int,
     *   anio: int,
     *   mes: int,
     *   lineas: list<RemesaLinea>,
     *   tesoreria_cents: int,
     *   remanente_cents: int,
     *   disponible_cents: int,
     *   hasta: string,
     *   emisor_iniciales: string,
     *   receptor_codigo: string
     * }
     */
    public function ejecutar(int $anio, int $mes): array
    {
        PeriodoPersonal::validar($anio, $mes);
        $ctx = $this->ambito->ejecutar();
        $periodo = $this->periodoPersonal->ejecutar($ctx->personaId, $anio, $mes);
        $desde = PeriodoPersonal::primerDia($anio, $mes);
        $hasta = \DateTimeImmutable::createFromFormat('!Y-m-d', $periodo['hasta']);
        if ($hasta === false) {
            throw new InvalidArgumentException(_("Periodo no válido"));
        }
        $ejercicio = $this->ejercicios->deCentroEnFecha($ctx->centroId, $desde);
        if ($ejercicio === null) {
            $ejercicio = $this->ejercicios->deCentroEnFecha($ctx->centroId, $hasta);
        }
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new InvalidArgumentException(_("No hay ejercicio que cubra ese mes"));
        }
        $destino = $this->destino($ctx, $hasta, $ejercicio);
        $ejercicioDestino = $destino['ejercicio'];
        PeriodoPersonal::fechaAsiento($desde, $hasta, $ejercicioDestino);

        $cuentas = [];
        foreach ($this->cuentas->listarDePersona($ctx->centroId, $ctx->personaId, 'X') as $c) {
            if ($c->id !== null) {
                $cuentas[$c->id] = $c;
            }
        }
        $asientos = $this->asientos->listar($ejercicio->id, [
            'libro' => 'X',
            'persona_id' => $ctx->personaId,
            'desde' => $periodo['desde'],
            'hasta' => $periodo['hasta'],
        ]);
        $lineas = AgregadorRemesaPersonal::agregar($asientos, $cuentas);
        $tesoreria = 0;
        foreach ($this->asientos->saldosPorCuenta(
            $ctx->centroId,
            (int) $ejercicio->id,
            null,
            $periodo['hasta'],
            'X',
        ) as $row) {
            if ((int) $row['persona_id'] !== $ctx->personaId) {
                continue;
            }
            if ($row['tipo'] === 'tesoreria' && in_array($row['codigo_maestro'], ['CAJA', 'BANCO'], true)) {
                $tesoreria += (int) $row['saldo_cents'];
            }
        }
        $remanente = $this->remanentes->dePersona($ctx->personaId);
        $personaDestino = $this->personas->porId($destino['persona_id']);
        $centroDestino = $this->centros->porId($destino['centro_id']);
        $iniciales = trim((string) ($personaDestino?->iniciales ?? ''));
        $codigoCentro = trim((string) ($centroDestino?->codigo ?? ''));
        if ($iniciales === '' || $codigoCentro === '') {
            throw new InvalidArgumentException(_("No se puede nombrar el emisor o el receptor de la remesa"));
        }

        return [
            'ctx' => $ctx,
            'ejercicio' => $ejercicioDestino,
            'destino_persona_id' => $destino['persona_id'],
            'destino_centro_id' => $destino['centro_id'],
            'emisor_iniciales' => $iniciales,
            'receptor_codigo' => $codigoCentro,
            'anio' => $anio,
            'mes' => $mes,
            'lineas' => $lineas,
            'tesoreria_cents' => $tesoreria,
            'remanente_cents' => $remanente,
            'disponible_cents' => CalculoDisponibleRemesa::cents($tesoreria, $remanente),
            'hasta' => $periodo['hasta'],
        ];
    }

    /**
     * El libro X sigue en la cuenta personal. Si esa cuenta es un libro propio
     * (centro tipo p) y hay un nombre vinculado a un centro, la remesa se dirige
     * a ese nombre para que el centro la vea en su bandeja.
     *
     * @return array{persona_id: int, centro_id: int, ejercicio: Ejercicio}
     */
    private function destino(ContextoPersonal $libro, DateTimeImmutable $hasta, Ejercicio $ejercicioLibro): array
    {
        $mismo = [
            'persona_id' => $libro->personaId,
            'centro_id' => $libro->centroId,
            'ejercicio' => $ejercicioLibro,
        ];
        $persona = $this->personas->porId($libro->personaId);
        $centro = $persona?->centroId !== null ? $this->centros->porId($persona->centroId) : null;
        if ($centro === null || $centro->tipo !== 'p' || $this->identidadId === null) {
            return $mismo;
        }
        $vinculos = $this->identidades->personasVinculoDe($this->identidadId);
        if ($vinculos === []) {
            return $mismo;
        }
        $vinculo = $vinculos[0];
        $ejercicio = $this->ejercicios->deCentroEnFecha($vinculo['centro_id'], $hasta);
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new InvalidArgumentException(_("No hay ejercicio que cubra ese mes"));
        }

        return [
            'persona_id' => $vinculo['persona_id'],
            'centro_id' => $vinculo['centro_id'],
            'ejercicio' => $ejercicio,
        ];
    }
}
