<?php

declare(strict_types=1);

namespace src\cierre\application;

use src\ambito\application\AsegurarCuentaDeudaVivienda;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\cierre\domain\services\ConstructorAsientosLiquidacionDeuda;
use src\cierre\domain\services\CuentasDeudaVivienda;
use src\cierre\domain\services\ImporteSaldableDeuda;
use src\cierre\domain\services\SuperavitVivienda;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

/**
 * El cierre deja la deuda de los meses atrasados. Si lo ingresado en vivienda
 * supera la cuota, ese superávit salda la deuda sin un 211 nuevo ni el saldo contable.
 * Al repetir el cierre se sustituyen estos asientos, porque la cuota se recalcula.
 */
final class AplicarSuperavitVivienda
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly ConfiguracionRepository $config,
        private readonly PersonaRepository $personas,
        private readonly CuentaRepository $cuentas,
        private readonly AsientoRepository $asientos,
        private readonly MedirSuperavitVivienda $medir,
        private readonly AsegurarCuentaDeudaVivienda $cuentasDeuda,
    ) {
    }

    /**
     * @return list<array{iniciales: string, superavit_es: string, saldaria_es: string}>
     */
    public function prevision(): array
    {
        $out = [];
        foreach ($this->planes() as $plan) {
            $out[] = [
                'iniciales' => $plan['persona']->iniciales,
                'superavit_es' => $plan['superavit']->formatEs(),
                'saldaria_es' => Dinero::fromCents($plan['cents'])->formatEs(),
            ];
        }

        return $out;
    }

    /** @return array{personas: int, asientos: int} */
    public function ejecutar(): array
    {
        $contexto = $this->ambito->ejecutar();
        if ($this->cuentas->deudoresVivienda($contexto->centroId) === null) {
            return ['personas' => 0, 'asientos' => 0];
        }

        $cfg = $this->config->get();
        $deudores = $this->cuentas->deudoresVivienda($contexto->centroId);
        $superG = $this->cuentasDeuda->superavitColectivo($contexto->centroId);
        if ($deudores === null || $deudores->id === null || $superG->id === null) {
            return ['personas' => 0, 'asientos' => 0];
        }

        $pares = [];
        $personas = 0;
        foreach ($this->planes() as $plan) {
            if ($plan['cents'] <= 0) {
                continue;
            }
            $persona = $plan['persona'];
            $deuda = $this->cuentasDeuda->ejecutar($persona);
            $superP = $this->cuentasDeuda->superavitDe($persona);
            if ($deuda->id === null || $superP->id === null || $persona->id === null) {
                continue;
            }
            $pares[] = ConstructorAsientosLiquidacionDeuda::construir(
                $contexto->ejercicioId,
                $persona->id,
                $cfg->fechaCierre,
                $deuda->id,
                $superP->id,
                $deudores->id,
                $superG->id,
                $plan['cents'],
                sprintf('Superávit de vivienda %s', strtolower($persona->iniciales)),
                CuentasDeudaVivienda::TIPO_SUPERAVIT,
            );
            ++$personas;
        }

        $asientos = $this->asientos->reemplazarSuperavitVivienda($contexto->ejercicioId, $pares);

        return ['personas' => $personas, 'asientos' => $asientos];
    }

    /**
     * @return list<array{persona: Persona, superavit: Dinero, cents: int}>
     */
    private function planes(): array
    {
        $contexto = $this->ambito->ejecutar();
        if ($this->cuentas->deudoresVivienda($contexto->centroId) === null) {
            return [];
        }

        $cfg = $this->config->get();
        $medicion = $this->medir->ejecutar($cfg->fechaCierre);
        $desde = $cfg->fechaInicio->format('Y-m-d');
        $hasta = $cfg->fechaCierre->format('Y-m-d');
        $deudaPorPersona = [];
        $aplicadoPorPersona = [];
        foreach ($this->asientos->saldosPorCuenta($contexto->centroId, $contexto->ejercicioId, $desde, $hasta) as $row) {
            if ($row['libro'] !== 'P' || $row['persona_id'] === null) {
                continue;
            }
            if ($row['codigo_maestro'] === CuentasDeudaVivienda::MAESTRO) {
                $deudaPorPersona[(int) $row['persona_id']] = (int) $row['saldo_cents'];
            }
            if ($row['codigo_maestro'] === CuentasDeudaVivienda::MAESTRO_SUPERAVIT) {
                $saldo = (int) $row['saldo_cents'];
                $aplicadoPorPersona[(int) $row['persona_id']] = $saldo < 0 ? -$saldo : 0;
            }
        }

        $out = [];
        foreach ($this->personas->listarDeCentro($contexto->centroId) as $p) {
            if ($p->id === null) {
                continue;
            }
            $ini = strtolower(trim($p->iniciales));
            $superavit = $medicion[$ini]['superavit'] ?? Dinero::zero();
            $deudaSin = SuperavitVivienda::deudaSinAplicar(
                $deudaPorPersona[$p->id] ?? 0,
                $aplicadoPorPersona[$p->id] ?? 0,
            );
            $cents = ImporteSaldableDeuda::partes($deudaSin, $superavit->toCents(), 0)['superavit'];
            $out[] = [
                'persona' => $p,
                'superavit' => $superavit,
                'cents' => $cents,
            ];
        }

        return $out;
    }
}
