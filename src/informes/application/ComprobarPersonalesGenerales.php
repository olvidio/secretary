<?php

declare(strict_types=1);

namespace src\informes\application;

use DateTimeImmutable;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\application\ListarApuntes;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\informes\domain\services\CuadreViviendaGenerales;
use src\informes\domain\services\MesesSinMovimiento;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;

final class ComprobarPersonalesGenerales
{
    public function __construct(
        private readonly ListarApuntes $listar,
        private readonly PersonaRepository $personas,
        private readonly ConfiguracionRepository $config,
        private readonly ResolverAmbitoActual $ambito,
        private readonly CuadreViviendaGenerales $cuadreVivienda,
        private readonly MesesSinMovimiento $mesesSinMovimiento,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(?string $hasta): array
    {
        $cfg = $this->config->get();
        $fechaHasta = $hasta ? new DateTimeImmutable($hasta) : $cfg->fechaCierre;
        $hastaStr = $fechaHasta->format('Y-m-d');
        $desde = $cfg->fechaInicio->format('Y-m-d');
        $contexto = $this->ambito->ejecutar();

        $personasVivienda = [];
        $personasMeses = [];
        foreach ($this->personas->listarDeCentro($contexto->centroId) as $p) {
            if (!$p->activo) {
                continue;
            }
            $personasVivienda[] = [
                'iniciales' => $p->iniciales,
                'nombre' => $p->nombreCompleto(),
                'aporta' => $p->viviendaAportaGenerales,
            ];
            $personasMeses[] = [
                'iniciales' => $p->iniciales,
                'nombre' => $p->nombreCompleto(),
                'exento_meses' => self::mesesExentos($p),
            ];
        }

        $rango = ['desde' => $desde, 'hasta' => $hastaStr, 'origen' => 'A'];
        $vivienda = $this->cuadreVivienda->ejecutar(
            $this->listar->ejecutar($rango + ['cuenta' => 'P', 'concepto' => '21']),
            $this->listar->ejecutar($rango + ['cuenta' => 'G', 'concepto' => '11']),
            $personasVivienda,
        );

        $sinMovimiento = $this->mesesSinMovimiento->ejecutar(
            $personasMeses,
            $this->listar->ejecutar(['desde' => $desde, 'hasta' => $hastaStr, 'es_cierre' => false]),
            $cfg->fechaInicio,
            $fechaHasta,
        );
        $nSin = count($sinMovimiento['por_persona']);

        $comprobaciones = [
            [
                'id' => 'vivienda_21_11',
                'titulo' => 'Vivienda P/21 y generales G/11',
                'ok' => $vivienda['ok'],
                'mensaje' => $vivienda['ok']
                    ? 'Las salidas P/21 de quienes aportan coinciden con las entradas G/11 ('
                        . $vivienda['total_p21_es'] . ' €).'
                    : 'Las salidas P/21 de quienes aportan son ' . $vivienda['total_p21_es']
                        . ' € y las entradas G/11 ' . $vivienda['total_g11_es']
                        . ' € (diferencia ' . $vivienda['diferencia_es'] . ' €).',
                'ayuda' => 'Quien tiene «vivienda aporta a generales» en Nombres debe tener '
                    . 'el mismo importe en apuntes A de P/21 y de G/11. Si no aporta, el 21 '
                    . 'es solo personal y no debe haber G/11 a su nombre.',
                'totales' => [
                    'p21_es' => $vivienda['total_p21_es'],
                    'g11_es' => $vivienda['total_g11_es'],
                    'diferencia_es' => $vivienda['diferencia_es'],
                ],
                'personas' => $vivienda['por_persona'],
                'avisos' => $vivienda['avisos'],
            ],
            [
                'id' => 'meses_sin_movimiento',
                'titulo' => 'Meses sin movimiento',
                'ok' => $sinMovimiento['ok'],
                'mensaje' => $sinMovimiento['ok']
                    ? 'Todas las personas tienen algún apunte en cada mes hasta la fecha de cierre (salvo exención).'
                    : $nSin . ' persona(s) sin movimiento en el mes de cierre o en meses anteriores.',
                'ayuda' => 'De la fecha de inicio al mes de la fecha de cierre. El cierre automático '
                    . 'y el 32 de apertura no cuentan. Quien está exento ese mes no se exige. '
                    . 'Quien no aporta a G debe dejar la exención vacía en Nombres para entrar aquí.',
                'personas' => $sinMovimiento['por_persona'],
            ],
        ];

        $ok = array_reduce(
            $comprobaciones,
            static fn (bool $carry, array $c): bool => $carry && ($c['ok'] ?? false),
            true,
        );

        return [
            'hasta' => $hastaStr,
            'ok' => $ok,
            'comprobaciones' => $comprobaciones,
        ];
    }

    /** @return list<int> */
    private static function mesesExentos(Persona $p): array
    {
        $out = [];
        for ($m = 1; $m <= 12; ++$m) {
            if ($p->exentaEnMes($m)) {
                $out[] = $m;
            }
        }

        return $out;
    }
}
