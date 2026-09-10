<?php

declare(strict_types=1);

namespace src\arqueo\infrastructure\http;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\arqueo\application\BuscarCapuchinos;
use src\arqueo\application\GuardarArqueo;
use src\arqueo\domain\contracts\ArqueoRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\informes\application\CalcularSaldos;
use src\shared\domain\value_objects\Dinero;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class ArqueoController
{
    public function __construct(
        private readonly ArqueoRepository $repo,
        private readonly GuardarArqueo $guardar,
        private readonly BuscarCapuchinos $buscarCapuchinos,
        private readonly CalcularSaldos $saldos,
        private readonly CuentaFisicaRepository $fisicas,
        private readonly AsientoRepository $asientos,
        private readonly ConfiguracionRepository $config,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    public function get(Request $request, array $vars): Response
    {
        $cuenta = strtoupper((string) ($vars['cuenta'] ?? 'G'));
        $arqueo = $this->repo->ultimo($cuenta);
        $saldosGlobales = $this->saldos->ejecutar(null);
        $cajaDefecto = $this->primeraCajaActiva();
        $desgloseFisico = $cajaDefecto !== null
            ? $this->saldosFisica($cajaDefecto->id)
            : ['saldo_fisico' => '0.00', 'saldo_caja_p' => '0.00', 'saldo_caja_g' => '0.00'];

        return ContestarJson::ok([
            'arqueo' => $arqueo?->toArray(),
            'saldo_caja' => $saldosGlobales['caja'],
            'saldo_banco' => $saldosGlobales['banco'],
            'saldo_fisico' => $desgloseFisico['saldo_fisico'],
            'saldo_caja_p' => $desgloseFisico['saldo_caja_p'],
            'saldo_caja_g' => $desgloseFisico['saldo_caja_g'],
            'cuenta_fisica_id' => $cajaDefecto?->id,
            'cajas_activas' => array_map(
                static fn ($f) => $f->toArray(),
                $this->fisicas->listarActivasDeCentro($this->ambito->ejecutar()->centroId, 'caja'),
            ),
        ]);
    }

    public function capuchinos(Request $request, array $vars): Response
    {
        try {
            $r = $this->buscarCapuchinos->ejecutar(
                (string) ($request->query('diferencia') ?? ''),
                $request->query('hasta'),
            );
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok($r);
    }

    public function save(Request $request, array $vars): Response
    {
        $cuenta = strtoupper((string) ($vars['cuenta'] ?? 'G'));
        $body = $request->json();
        $fecha = (string) ($body['fecha'] ?? date('Y-m-d'));
        $cajaDefecto = $this->primeraCajaActiva();
        $fisicaId = isset($body['cuenta_fisica_id']) ? (int) $body['cuenta_fisica_id'] : $cajaDefecto?->id;
        try {
            $arqueo = $this->guardar->ejecutar($cuenta, $fecha, $body['desglose'] ?? [], $fisicaId);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok(['arqueo' => $arqueo->toArray()]);
    }

    public function getFisica(Request $request, array $vars): Response
    {
        $fisicaId = (int) ($vars['id'] ?? 0);
        if ($fisicaId <= 0) {
            return ContestarJson::error('Identificador de cuenta física no válido');
        }
        $fisica = $this->fisicas->porId($fisicaId);
        if ($fisica === null) {
            return ContestarJson::error('Cuenta física no encontrada');
        }
        $arqueo = $this->repo->ultimoPorFisica($fisicaId);
        $desglose = $this->saldosFisica($fisicaId);

        return ContestarJson::ok([
            'arqueo' => $arqueo?->toArray(),
            'fisica' => $fisica->toArray(),
            ...$desglose,
        ]);
    }

    public function saveFisica(Request $request, array $vars): Response
    {
        $fisicaId = (int) ($vars['id'] ?? 0);
        $fisica = $this->fisicas->porId($fisicaId);
        if ($fisica === null) {
            return ContestarJson::error('Cuenta física no encontrada');
        }
        $body = $request->json();
        $fecha = (string) ($body['fecha'] ?? date('Y-m-d'));
        $cuentaLegado = $fisica->tipo === 'caja' ? 'C' : 'B';
        try {
            $arqueo = $this->guardar->ejecutar($cuentaLegado, $fecha, $body['desglose'] ?? [], $fisicaId);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok(['arqueo' => $arqueo->toArray()]);
    }

    /** @return array{saldo_fisico:string,saldo_caja_p:string,saldo_caja_g:string,saldo_fisico_es?:string} */
    private function saldosFisica(int $fisicaId): array
    {
        $contexto = $this->ambito->ejecutar();
        $cfg = $this->config->get();
        $desde = $cfg->fechaInicio->format('Y-m-d');
        $hasta = $cfg->fechaCierre->format('Y-m-d');
        $saldoP = Dinero::zero();
        $saldoG = Dinero::zero();
        foreach ($this->asientos->saldosPorCuenta($contexto->centroId, $contexto->ejercicioId, $desde, $hasta) as $row) {
            if ($row['tipo'] !== 'tesoreria' || $row['cuenta_fisica_id'] !== $fisicaId) {
                continue;
            }
            $saldo = Dinero::fromCents($row['saldo_cents']);
            if ($row['libro'] === 'P') {
                $saldoP = $saldo;
            } elseif ($row['libro'] === 'G') {
                $saldoG = $saldo;
            }
        }
        $fisico = $saldoP->add($saldoG);

        return [
            'saldo_fisico' => $fisico->toString(),
            'saldo_fisico_es' => $fisico->formatEs(),
            'saldo_caja_p' => $saldoP->toString(),
            'saldo_caja_g' => $saldoG->toString(),
        ];
    }

    private function primeraCajaActiva(): ?\src\ambito\domain\entity\CuentaFisica
    {
        $cajas = $this->fisicas->listarActivasDeCentro($this->ambito->ejecutar()->centroId, 'caja');

        return $cajas[0] ?? null;
    }
}
