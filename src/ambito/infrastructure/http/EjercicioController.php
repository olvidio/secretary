<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\ambito\application\CrearEjercicio;
use src\ambito\application\EliminarEjercicio;
use src\ambito\application\ListarEjercicios;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Centro;
use src\ambito\domain\services\PeriodoEjercicioTipico;
use src\presupuestos\domain\services\SiguientePeriodoEjercicio;
use src\cierre\application\CerrarEjercicio;
use src\cierre\application\GenerarApertura;
use src\cierre\application\ReabrirEjercicio;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

/**
 * UI mínima de alta de ejercicios de período libre (D11) y cierre/apertura (D12).
 * De momento opera sobre el primer centro activo: no hay todavía
 * selector de centro en la interfaz (multicentro operativo es la Fase 9).
 */
final class EjercicioController
{
    public function __construct(
        private readonly ListarEjercicios $listar,
        private readonly CrearEjercicio $crear,
        private readonly CerrarEjercicio $cerrar,
        private readonly ReabrirEjercicio $reabrir,
        private readonly GenerarApertura $generarApertura,
        private readonly EliminarEjercicio $eliminar,
        private readonly CentroRepository $centros,
        private readonly EjercicioRepository $ejercicios,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        $centro = $this->centroActual();
        if ($centro === null) {
            return ContestarJson::ok(['ejercicios' => [], 'centro' => null]);
        }

        $lista = $this->listar->ejecutar($centro->id);

        return ContestarJson::ok([
            'ejercicios' => $lista,
            'centro' => $centro->toArray(),
            'sugerencia_nuevo' => $this->sugerenciaNuevoEjercicio($centro->id, $lista),
        ]);
    }

    /** @param list<array<string, mixed>> $lista */
    private function sugerenciaNuevoEjercicio(int $centroId, array $lista): array
    {
        $abierto = $this->ejercicios->abiertoDe($centroId);
        if ($abierto !== null) {
            return $this->sugerenciaDesdeSiguiente(SiguientePeriodoEjercicio::calcular($abierto));
        }
        $ultimo = null;
        foreach ($lista as $fila) {
            if ($ultimo === null || ($fila['fecha_fin'] ?? '') > ($ultimo['fecha_fin'] ?? '')) {
                $ultimo = $fila;
            }
        }
        if ($ultimo !== null && isset($ultimo['id'])) {
            $ej = $this->ejercicios->porId((int) $ultimo['id']);
            if ($ej !== null && $ej->estado === 'cerrado') {
                return $this->sugerenciaDesdeSiguiente(SiguientePeriodoEjercicio::calcular($ej));
            }
        }
        $anio = (int) date('Y');
        [$ini, $fin] = PeriodoEjercicioTipico::fechas($anio, PeriodoEjercicioTipico::MODO_ANO);

        return [
            'modo_ejercicio' => PeriodoEjercicioTipico::MODO_ANO,
            'anio' => $anio,
            'fecha_inicio' => $ini->format('Y-m-d'),
            'fecha_fin' => $fin->format('Y-m-d'),
        ];
    }

    /**
     * @param array{etiqueta: string, fecha_inicio: \DateTimeImmutable, fecha_fin: \DateTimeImmutable} $sig
     *
     * @return array{modo_ejercicio: string, anio: int, fecha_inicio: string, fecha_fin: string}
     */
    private function sugerenciaDesdeSiguiente(array $sig): array
    {
        $modo = PeriodoEjercicioTipico::modoDesdeFechas($sig['fecha_inicio'], $sig['fecha_fin']);
        $anio = (int) $sig['fecha_inicio']->format('Y');

        return [
            'modo_ejercicio' => $modo,
            'anio' => $anio,
            'fecha_inicio' => $sig['fecha_inicio']->format('Y-m-d'),
            'fecha_fin' => $sig['fecha_fin']->format('Y-m-d'),
        ];
    }

    public function create(Request $request, array $vars = []): Response
    {
        $centro = $this->centroActual();
        if ($centro === null) {
            return ContestarJson::error(_("No hay ningún centro dado de alta todavía"));
        }
        try {
            $datos = $request->json();
            $datos['centro_id'] = $centro->id;
            $ejercicio = $this->crear->ejecutar($datos);

            return ContestarJson::ok(['ejercicio' => $ejercicio->toArray()]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function cerrar(Request $request, array $vars = []): Response
    {
        try {
            $ejercicio = $this->cerrar->ejecutar((int) ($vars['id'] ?? 0));

            return ContestarJson::ok(['ejercicio' => $ejercicio->toArray()]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function reabrir(Request $request, array $vars = []): Response
    {
        try {
            $ejercicio = $this->reabrir->ejecutar((int) ($vars['id'] ?? 0));

            return ContestarJson::ok(['ejercicio' => $ejercicio->toArray()]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function eliminar(Request $request, array $vars = []): Response
    {
        $centro = $this->centroActual();
        if ($centro === null) {
            return ContestarJson::error(_('No hay ningún centro dado de alta todavía'));
        }
        try {
            $this->eliminar->ejecutar((int) ($vars['id'] ?? 0), $centro->id);

            return ContestarJson::ok(['eliminado' => true]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function apertura(Request $request, array $vars = []): Response
    {
        try {
            $asientos = $this->generarApertura->ejecutar((int) ($vars['id'] ?? 0));

            return ContestarJson::ok([
                'asientos' => array_map(static fn ($a) => $a->toArray(), $asientos),
            ]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    private function centroActual(): ?Centro
    {
        try {
            $contexto = $this->ambito->ejecutar();
        } catch (\Throwable) {
            return $this->centros->listar()[0] ?? null;
        }
        foreach ($this->centros->listar() as $centro) {
            if ($centro->id === $contexto->centroId) {
                return $centro;
            }
        }

        return null;
    }
}
