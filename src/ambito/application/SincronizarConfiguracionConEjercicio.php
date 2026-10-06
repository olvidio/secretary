<?php

declare(strict_types=1);

namespace src\ambito\application;

use src\ambito\domain\entity\Ejercicio;
use src\ambito\domain\services\PeriodoEjercicioTipico;
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
        return PeriodoEjercicioTipico::modoDesdeFechas($ejercicio->fechaInicio, $ejercicio->fechaFin);
    }

    /** @return array{anio:int,modo:string,fecha_inicio:DateTimeImmutable,fecha_corte:DateTimeImmutable} */
    public static function legadoDesdeEjercicio(Ejercicio $ejercicio): array
    {
        return [
            'anio' => self::anioDesdeEjercicio($ejercicio),
            'modo' => self::modoDesdeEjercicio($ejercicio),
            'fecha_inicio' => $ejercicio->fechaInicio,
            'fecha_corte' => $ejercicio->fechaCorte,
        ];
    }
}
