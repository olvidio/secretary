<?php

declare(strict_types=1);

namespace src\informes\application;

use DateTimeImmutable;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\application\CalcularCuadreApuntesA;
use src\asientos\domain\contracts\AsientoRepository;
use src\informes\domain\services\EstadoCuentaPersonal;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\shared\domain\value_objects\Dinero;

final class ComprobarSaldos
{
    public function __construct(
        private readonly CalcularSaldos $saldos,
        private readonly CalcularCuadreApuntesA $cuadreApuntesA,
        private readonly AsientoRepository $asientos,
        private readonly ConfiguracionRepository $config,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(?string $hasta): array
    {
        $cfg = $this->config->get();
        $fechaHasta = $hasta ? new DateTimeImmutable($hasta) : $cfg->fechaCierre;
        $hastaStr = $fechaHasta->format('Y-m-d');

        $saldosData = $this->saldos->ejecutar($hastaStr);
        $contexto = $this->ambito->ejecutar();
        $hayDescuadrados = $this->asientos->hayDescuadrados($contexto->ejercicioId);

        $saldoGlobal = Dinero::fromInput($saldosData['saldo_a']);
        $porDestinar = [];
        $personas = $this->personasConProblemas($saldosData['por_persona'], $hastaStr, $porDestinar);

        $comprobaciones = [
            $this->checkPartidaDoble($hayDescuadrados),
            $this->checkSaldoGlobal($saldoGlobal, $saldosData['saldo_a_es'], $porDestinar),
            $this->checkDeuda($saldosData['por_persona']),
            $this->checkApuntesA($personas),
            $this->checkPorDestinar($porDestinar),
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
     * @param list<array<string, mixed>> $porPersona
     * @param list<array{iniciales: string, nombre: string, saldo_cents: int, saldo_es: string}> $porDestinar
     *        salida: c/c positiva con los apuntes A cuadrados (sobrante de remesas sin destinar)
     * @return list<array<string, mixed>>
     */
    private function personasConProblemas(array $porPersona, string $fecha, array &$porDestinar = []): array
    {
        $out = [];

        foreach ($porPersona as $p) {
            $saldoCuenta = Dinero::fromInput((string) $p['saldo_a']);
            $cuadre = $this->cuadreApuntesA->ejecutar('P', (string) $p['iniciales'], $fecha);
            $cuadrado = !$cuadre['aplica'] || ($cuadre['cuadrado'] ?? false);

            $estado = EstadoCuentaPersonal::de($cuadrado, $saldoCuenta->toCents());
            if ($estado === EstadoCuentaPersonal::OK) {
                continue;
            }
            if ($estado === EstadoCuentaPersonal::POR_DESTINAR) {
                $porDestinar[] = [
                    'iniciales' => (string) $p['iniciales'],
                    'nombre' => (string) $p['nombre'],
                    'saldo_cents' => $saldoCuenta->toCents(),
                    'saldo_es' => $saldoCuenta->formatEs(),
                ];
                continue;
            }

            $coherente = true;
            $nota = null;
            if ($cuadre['aplica'] ?? false) {
                $saldoApuntes = Dinero::fromInput((string) $cuadre['saldo_total']);
                $coherente = ($saldoCuenta->toCents() + $saldoApuntes->toCents()) === 0;
                if (!$coherente) {
                    $nota = 'El saldo de la cuenta personal no cuadra con la suma de apuntes A '
                        . '(puede haber cierres, aperturas u otros asientos fuera de los apuntes A).';
                }
            }

            $item = [
                'iniciales' => $p['iniciales'],
                'nombre' => $p['nombre'],
                'saldo_cuenta_es' => $p['saldo_a_es'],
                'coherente' => $coherente,
                'nota' => $nota,
            ];

            if ($cuadre['aplica'] ?? false) {
                $item['cuadre'] = [
                    'cuadrado' => $cuadre['cuadrado'],
                    'saldo_apuntes_es' => $cuadre['saldo_total_es'],
                    'sugerencia' => $cuadre['sugerencia'],
                ];
            }

            $out[] = $item;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function checkPartidaDoble(bool $hayDescuadrados): array
    {
        return [
            'id' => 'partida_doble',
            'titulo' => 'Partida doble',
            'ok' => !$hayDescuadrados,
            'mensaje' => $hayDescuadrados
                ? 'Hay asientos con debe ≠ haber. Los saldos no son fiables hasta corregirlos.'
                : 'Todos los asientos cuadran (debe = haber).',
        ];
    }

    /**
     * @param list<array{iniciales: string, nombre: string, saldo_cents: int, saldo_es: string}> $porDestinar
     * @return array<string, mixed>
     */
    private function checkSaldoGlobal(Dinero $saldoGlobal, string $saldoEs, array $porDestinar = []): array
    {
        $sobrante = array_sum(array_column($porDestinar, 'saldo_cents'));
        $resto = Dinero::fromCents($saldoGlobal->toCents() - $sobrante);
        $ok = $resto->isZero();
        if ($saldoGlobal->isZero()) {
            $mensaje = 'La suma de todas las cuentas personales (libro P) es cero.';
        } elseif ($ok) {
            $mensaje = sprintf(
                _('La suma de cuentas personales es %s €: es el sobrante de remesas pendiente de destinar a partidas 7 (ver «Sobrante de remesas por destinar»).'),
                $saldoEs,
            );
        } else {
            $mensaje = 'La suma de cuentas personales es ' . $saldoEs . ' €; debería ser 0'
                . ($sobrante !== 0
                    ? sprintf(_(' (descontado el sobrante de remesas por destinar, quedan %s €).'), $resto->formatEs())
                    : '.');
        }

        return [
            'id' => 'saldo_a_global',
            'titulo' => 'Saldo global cuentas personales',
            'ok' => $ok,
            'valor_es' => $saldoEs,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * Informativo: c/c positiva con los apuntes A cuadrados es lo que le queda a
     * la persona de sus remesas, hasta destinarlo a partidas 7.
     *
     * @param list<array{iniciales: string, nombre: string, saldo_cents: int, saldo_es: string}> $porDestinar
     * @return array<string, mixed>
     */
    private function checkPorDestinar(array $porDestinar): array
    {
        $n = count($porDestinar);
        $personas = [];
        foreach ($porDestinar as $p) {
            $personas[] = [
                'iniciales' => $p['iniciales'],
                'nombre' => $p['nombre'],
                'saldo_cuenta_es' => $p['saldo_es'],
                'coherente' => true,
                'nota' => _('Sobrante de sus remesas, pendiente de destinar a partidas 7 (Disponible).'),
            ];
        }

        return [
            'id' => 'sobrante_por_destinar',
            'titulo' => _('Sobrante de remesas por destinar'),
            'ok' => true,
            'informativo' => true,
            'mensaje' => $n === 0
                ? _('Nadie tiene sobrante de remesas pendiente en su cuenta personal.')
                : sprintf(_('%d persona(s) con sobrante de remesas en su cuenta personal, pendiente de destinar a partidas 7. No es un error.'), $n),
            'ayuda' => _('Al aceptar una remesa, lo que sobra de ingresos menos gastos se queda en la cuenta personal (saldo contable) y en el disponible. Se destina en Disponible → Proponer destinos 7; cuando llega la remesa con esas partidas 7, la cuenta personal baja en ese importe.'),
            'personas' => $personas,
        ];
    }

    /**
     * @param list<array<string, mixed>> $personas
     * @return array<string, mixed>
     */
    private function checkApuntesA(array $personas): array
    {
        $n = count($personas);

        return [
            'id' => 'apuntes_a',
            'titulo' => 'Apuntes A por persona',
            'ok' => $n === 0,
            'mensaje' => $n === 0
                ? _('Cada persona tiene sus apuntes A cuadrados (ingresos = gastos) y la cuenta personal a cero o solo con sobrante de remesas por destinar.')
                : $n . ' persona(s) con saldo distinto de cero o apuntes A sin cuadrar.',
            'personas' => $personas,
            'ayuda' => 'Los apuntes A (sin caja/banco) deben cuadrar por persona: cada gasto '
                . 'personal lleva su contrapartida, normalmente un ingreso 111 (Trabajo). '
                . 'El cierre de vivienda no entra en este cuadre: queda en la deuda de vivienda '
                . 'y se salda aparte, sin un 111. '
                . 'Si sobran ingresos, suele faltar el gasto de vivienda: P/211 si aporta a generales '
                . '(cuadra con G/11) o P/212 si es vivienda personal.',
        ];
    }

    /**
     * @param list<array<string, mixed>> $porPersona
     * @return array<string, mixed>
     */
    private function checkDeuda(array $porPersona): array
    {
        $n = 0;
        foreach ($porPersona as $p) {
            if (Dinero::fromInput((string) ($p['deuda'] ?? '0'))->isNegative()) {
                ++$n;
            }
        }

        return [
            'id' => 'deuda_vivienda',
            'titulo' => 'Deuda de vivienda',
            'ok' => $n === 0,
            'mensaje' => $n === 0
                ? 'Ninguna persona tiene deuda de vivienda pendiente del cierre.'
                : $n . ' persona(s) con deuda de vivienda del cierre. En Saldos, «Saldar»: primero el superávit de vivienda y, si no alcanza, el saldo contable. No aumenta el 111.',
            'ayuda' => 'El cierre anota la vivienda (211 y 11) y deja lo pendiente en la cuenta de deuda, '
                . 'fuera del saldo contable. Si lo ingresado en vivienda supera la cuota, ese superávit salda la deuda. '
                . 'Lo que falte sale del saldo contable positivo.',
        ];
    }
}
