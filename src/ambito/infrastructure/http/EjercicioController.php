<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\ambito\application\CrearEjercicio;
use src\ambito\application\ListarEjercicios;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
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
        private readonly CentroRepository $centros,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        $centro = $this->centroActual();
        if ($centro === null) {
            return ContestarJson::ok(['ejercicios' => [], 'centro' => null]);
        }

        return ContestarJson::ok([
            'ejercicios' => $this->listar->ejecutar($centro->id),
            'centro' => $centro->toArray(),
        ]);
    }

    public function create(Request $request, array $vars = []): Response
    {
        $centro = $this->centroActual();
        if ($centro === null) {
            return ContestarJson::error('No hay ningún centro dado de alta todavía');
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
