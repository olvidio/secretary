<?php

declare(strict_types=1);

namespace src\ambito\application;

use src\ambito\domain\entity\Ejercicio;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;

/** Alinea el singleton `configuracion` legado con el ejercicio abierto de trabajo. */
final class SincronizarConfiguracionConEjercicio
{
    public function __construct(private readonly ConfiguracionRepository $config)
    {
    }

    public function ejecutar(Ejercicio $ejercicio): void
    {
        $actual = $this->config->get();
        $anio = self::anioDesdeEjercicio($ejercicio);
        $modo = self::modoDesdeEjercicio($ejercicio);

        $this->config->guardar(new ConfiguracionCentro(
            $actual->centro,
            $anio,
            $modo,
            $ejercicio->fechaInicio,
            $ejercicio->fechaCorte,
            $actual->tipoCierre,
            $actual->numResidentes,
            $actual->version,
            $actual->observaciones613P,
            $actual->observaciones613G,
            $actual->mediaCocinaMes,
            $actual->mediaCocinaAcum,
            $actual->saldoCcPersonales,
        ));
    }

    private static function anioDesdeEjercicio(Ejercicio $ejercicio): int
    {
        if (ctype_digit($ejercicio->etiqueta)) {
            return (int) $ejercicio->etiqueta;
        }

        return (int) $ejercicio->fechaInicio->format('Y');
    }

    private static function modoDesdeEjercicio(Ejercicio $ejercicio): string
    {
        $inicioAnio = (int) $ejercicio->fechaInicio->format('Y');
        $fin = $ejercicio->fechaFin;
        if ($fin->format('m-d') === '12-31' && (int) $fin->format('Y') === $inicioAnio) {
            return 'Año';
        }
        if ($fin->format('m-d') === '08-31' && (int) $fin->format('Y') === $inicioAnio + 1) {
            return 'Curso';
        }

        return 'Año';
    }
}
