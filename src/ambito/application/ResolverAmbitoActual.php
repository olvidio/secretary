<?php

declare(strict_types=1);

namespace src\ambito\application;

use RuntimeException;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\value_objects\ContextoActual;
use src\configuracion\domain\contracts\ConfiguracionRepository;

/** Resuelve centro y ejercicio abiertos. Prefiere el centro de sesión (D7). */
final class ResolverAmbitoActual
{
    public function __construct(
        private readonly ConfiguracionRepository $config,
        private readonly CentroRepository $centros,
        private readonly EjercicioRepository $ejercicios,
        private readonly ?int $centroSesionId = null,
    ) {
    }

    public function ejecutar(): ContextoActual
    {
        $centro = null;
        if ($this->centroSesionId !== null) {
            $centro = $this->centros->porId($this->centroSesionId);
        }
        if ($centro === null) {
            $cfg = $this->config->get();
            $codigo = trim($cfg->centro);
            $centro = $codigo !== '' ? $this->centros->porCodigo($codigo) : null;
            $centro ??= $this->centros->listar()[0] ?? null;
        }
        if ($centro === null || $centro->id === null) {
            throw new RuntimeException('No hay ningún centro configurado; ejecute db:install o db:migrate primero.');
        }
        $ejercicio = $this->ejercicios->abiertoDe($centro->id);
        if ($ejercicio === null || $ejercicio->id === null) {
            throw new RuntimeException('El centro no tiene ningún ejercicio abierto.');
        }

        return new ContextoActual($centro->id, $ejercicio->id);
    }
}
