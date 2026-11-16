<?php

declare(strict_types=1);

namespace src\informes\application;

use DateTimeImmutable;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\application\ListarApuntes;
use src\asientos\domain\contracts\AsientoRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\disponible\domain\services\DetectorLaboresDuplicadas;
use src\shared\domain\value_objects\Dinero;
use src\informes\domain\services\CuadreViviendaGenerales;
use src\informes\domain\services\DesfaseImputacionVivienda;
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
        private readonly AsientoRepository $asientos,
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
        $apuntesPVivienda = [];
        foreach (CuadreViviendaGenerales::conceptosPViviendaGeneral() as $concepto) {
            foreach ($this->listar->ejecutar($rango + ['cuenta' => 'P', 'concepto' => $concepto]) as $fila) {
                $apuntesPVivienda[] = $fila;
            }
        }
        $apuntesG11 = $this->listar->ejecutar($rango + ['cuenta' => 'G', 'concepto' => '11']);
        $vivienda = $this->cuadreVivienda->ejecutar($apuntesPVivienda, $apuntesG11, $personasVivienda);
        $vivienda['por_persona'] = $this->anotarDesfases($vivienda['por_persona'], $apuntesPVivienda, $apuntesG11, $fechaHasta);

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
                'titulo' => 'Vivienda P/211 y generales G/11',
                'ok' => $vivienda['ok'],
                'mensaje' => $vivienda['ok']
                    ? 'Las salidas P/211 de quienes aportan coinciden con las entradas G/11 ('
                        . $vivienda['total_p21_es'] . ' €).'
                    : 'Las salidas P/211 de quienes aportan son ' . $vivienda['total_p21_es']
                        . ' € y las entradas G/11 ' . $vivienda['total_g11_es']
                        . ' € (diferencia ' . $vivienda['diferencia_es'] . ' €).'
                        . (self::hayNotas($vivienda['por_persona'])
                            ? ' ' . _('Parte de la diferencia es solo de fechas de imputación: ver la nota bajo cada persona.')
                            : ''),
                'ayuda' => 'P/211 es la vivienda general (cierre automático o imputación puntual a generales). '
                    . 'Debe cuadrar con G/11. P/212 (vivienda personal) es un gasto propio, sin G/11, '
                    . 'y no entra aquí. Quien no entra en el cierre automático puede igualmente imputar a generales puntualmente (211/11).',
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
            $this->comprobarLaboresDuplicadas($contexto->centroId, $contexto->ejercicioId, $hastaStr, $personasVivienda),
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

    /**
     * @param list<array{iniciales: string, nombre: string, aporta: bool}> $personas
     * @return array<string, mixed>
     */
    private function comprobarLaboresDuplicadas(int $centroId, int $ejercicioId, string $hasta, array $personas): array
    {
        $nombres = [];
        foreach ($personas as $p) {
            $nombres[strtolower($p['iniciales'])] = $p['nombre'];
        }
        $pares = DetectorLaboresDuplicadas::detectar($this->asientos->movimientosLabores($centroId, $ejercicioId, $hasta));
        $total = 0;
        $duplicados = [];
        foreach ($pares as $par) {
            $total += $par['cents'];
            $duplicados[] = [
                'iniciales' => $par['iniciales'],
                'nombre' => $nombres[strtolower($par['iniciales'])] ?? $par['iniciales'],
                'codigo' => $par['codigo'],
                'importe_es' => Dinero::fromCents($par['cents'])->formatEs(),
                'asignacion' => [
                    'asiento_id' => $par['asignacion']['asiento_id'],
                    'numero' => $par['asignacion']['numero'],
                    'fecha' => $par['asignacion']['fecha'],
                ],
                'otro' => [
                    'asiento_id' => $par['otro']['asiento_id'],
                    'numero' => $par['otro']['numero'],
                    'fecha' => $par['otro']['fecha'],
                    'tipo' => $par['otro']['tipo'],
                ],
            ];
        }
        $ok = $duplicados === [];

        return [
            'id' => 'labores_duplicadas',
            'titulo' => _('Partidas 7 apuntadas dos veces'),
            'ok' => $ok,
            'mensaje' => $ok
                ? _('Ninguna asignación de destinos 7 coincide con otro apunte de la misma persona, partida e importe.')
                : sprintf(
                    _('%d asignación(es) de destinos 7 coinciden con otro apunte de la misma persona, partida e importe (%s € en total): ese gasto 7 cuenta dos veces.'),
                    count($duplicados),
                    Dinero::fromCents($total)->formatEs(),
                ),
            'ayuda' => _('Pasa con asignaciones confirmadas antes de que los gastos 7 se apuntaran al aceptar la remesa: el gasto se anotó al confirmar y otra vez cuando llegó la remesa con la transferencia. «Anular asignación» quita la asignación y su liquidación de c/c: el gasto queda una sola vez (el de la remesa), el 111 no cambia y la c/c de la persona recupera ese importe. Si los dos apuntes son de verdad dos donativos distintos por el mismo importe, no hay que anular nada.'),
            'duplicados' => $duplicados,
        ];
    }

    /**
     * Añade a cada persona las notas de desfase de fechas (P/211 y G/11 que se
     * cuentan en meses distintos por una fecha de imputación puesta solo en uno).
     *
     * @param list<array<string, mixed>> $porPersona
     * @param list<array<string, mixed>> $pDentro
     * @param list<array<string, mixed>> $gDentro
     * @return list<array<string, mixed>>
     */
    private function anotarDesfases(array $porPersona, array $pDentro, array $gDentro, DateTimeImmutable $hasta): array
    {
        $despues = [
            'desde' => $hasta->modify('+1 day')->format('Y-m-d'),
            'hasta' => $hasta->modify('+1 year')->format('Y-m-d'),
            'origen' => 'A',
        ];
        $pDespues = [];
        foreach (CuadreViviendaGenerales::conceptosPViviendaGeneral() as $concepto) {
            foreach ($this->listar->ejecutar($despues + ['cuenta' => 'P', 'concepto' => $concepto]) as $fila) {
                $pDespues[] = $fila;
            }
        }
        $notas = DesfaseImputacionVivienda::notas(
            $pDentro,
            $gDentro,
            $pDespues,
            $this->listar->ejecutar($despues + ['cuenta' => 'G', 'concepto' => '11']),
            $hasta->format('Y-m-d'),
        );
        foreach ($porPersona as $i => $p) {
            $ini = strtolower((string) ($p['iniciales'] ?? ''));
            if (isset($notas[$ini])) {
                $porPersona[$i]['notas'] = $notas[$ini];
            }
        }

        return $porPersona;
    }

    /** @param list<array<string, mixed>> $porPersona */
    private static function hayNotas(array $porPersona): bool
    {
        foreach ($porPersona as $p) {
            if (!empty($p['notas'])) {
                return true;
            }
        }

        return false;
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
