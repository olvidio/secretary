<?php

declare(strict_types=1);

namespace src\configuracion\infrastructure\persistence;

use src\ambito\application\SincronizarConfiguracionConEjercicio;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Centro;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;

/**
 * `configuracion` es una sola fila para toda la base, pero casi todo lo que
 * cuenta es de cada centro: el período (año, modo, fecha de inicio y de cierre)
 * vive en su ejercicio abierto (`fecha_corte`); el tipo de cierre y la sigla,
 * en `centros`. Al leer se toman del centro de la sesión
 * (sin sesión, el primer centro); así un centro nunca ve los datos de otro.
 *
 * Resuelve el centro como ResolverAmbitoActual, sin depender de él (que a su
 * vez lee la configuración).
 */
final class ConfiguracionDelEjercicio implements ConfiguracionRepository
{
    public function __construct(
        private readonly ConfiguracionRepository $base,
        private readonly CentroRepository $centros,
        private readonly EjercicioRepository $ejercicios,
        private readonly ?int $centroSesionId = null,
    ) {
    }

    public function get(): ConfiguracionCentro
    {
        $cfg = $this->base->get();
        $centro = $this->centroDeTrabajo();
        if ($centro === null || $centro->id === null) {
            return $cfg;
        }
        $tipoCierre = in_array($centro->tipoCierre, ['vivienda', 'necesidades'], true)
            ? $centro->tipoCierre
            : $cfg->tipoCierre;
        $ejercicio = $this->ejercicios->abiertoDe($centro->id);
        if ($ejercicio === null) {
            return new ConfiguracionCentro(
                $centro->codigo,
                $cfg->anio,
                $cfg->modoEjercicio,
                $cfg->fechaInicio,
                $cfg->fechaCierre,
                $tipoCierre,
                $cfg->version,
            );
        }
        $periodo = SincronizarConfiguracionConEjercicio::legadoDesdeEjercicio($ejercicio);

        return new ConfiguracionCentro(
            $centro->codigo,
            $periodo['anio'],
            $periodo['modo'],
            $periodo['fecha_inicio'],
            $periodo['fecha_corte'],
            $tipoCierre,
            $cfg->version,
        );
    }

    public function guardar(ConfiguracionCentro $config): void
    {
        $this->base->guardar($config);
    }

    private function centroDeTrabajo(): ?Centro
    {
        $centro = $this->centroSesionId !== null ? $this->centros->porId($this->centroSesionId) : null;

        // Sin sesión (consola, tareas): el primer centro dado de alta.
        return $centro ?? $this->centros->listar()[0] ?? null;
    }
}
