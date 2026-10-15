<?php

declare(strict_types=1);

namespace src\cierre\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\AsegurarCuentaDeudaVivienda;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\contracts\AsientoRepository;
use src\cierre\domain\services\ConstructorAsientosLiquidacionDeuda;
use src\cierre\domain\services\CuentasDeudaVivienda;
use src\cierre\domain\services\ImporteSaldableDeuda;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

/** Deja a cero la deuda de vivienda de una persona sin anotar trabajo (111). */
final class SaldarDeudaVivienda
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly ConfiguracionRepository $config,
        private readonly PersonaRepository $personas,
        private readonly CuentaRepository $cuentas,
        private readonly AsientoRepository $asientos,
        private readonly AsegurarCuentaDeudaVivienda $cuentasDeuda,
        private readonly MedirSuperavitVivienda $medir,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function ejecutar(array $datos): array
    {
        $iniciales = trim((string) ($datos['iniciales'] ?? ''));
        if ($iniciales === '') {
            throw new InvalidArgumentException(_("Faltan las iniciales"));
        }
        $contexto = $this->ambito->ejecutar();
        $persona = $this->personas->porInicialesDeCentro($contexto->centroId, $iniciales);
        if ($persona === null || $persona->id === null) {
            throw new InvalidArgumentException(_("Persona no encontrada en este centro"));
        }

        $cfg = $this->config->get();
        $hasta = trim((string) ($datos['hasta'] ?? ''));
        $fecha = $hasta !== '' ? new DateTimeImmutable($hasta) : $cfg->fechaCierre;
        $deuda = $this->cuentasDeuda->ejecutar($persona);
        $cc = $this->cuentas->personalDe($contexto->centroId, $persona->id);
        $superP = $this->cuentasDeuda->superavitDe($persona);
        $superG = $this->cuentasDeuda->superavitColectivo($contexto->centroId);
        $regG = $this->cuentasDeuda->regularizacion($contexto->centroId, 'G');
        $deudores = $this->deudores($contexto->centroId);
        if (
            $deuda->id === null || $cc === null || $cc->id === null
            || $superP->id === null || $superG->id === null
            || $regG->id === null || $deudores->id === null
        ) {
            throw new InvalidArgumentException(_("Faltan cuentas para saldar la deuda"));
        }

        $desde = $cfg->fechaInicio->format('Y-m-d');
        $hastaSaldo = $fecha->format('Y-m-d');
        $saldoDeuda = $this->saldoCuenta($contexto->centroId, $contexto->ejercicioId, $desde, $hastaSaldo, $deuda->id);
        $saldoCc = $this->saldoCuenta($contexto->centroId, $contexto->ejercicioId, $desde, $hastaSaldo, $cc->id);
        $saldoSuper = $this->saldoCuenta($contexto->centroId, $contexto->ejercicioId, $desde, $hastaSaldo, $superP->id);
        $adeudado = $saldoDeuda < 0 ? -$saldoDeuda : 0;
        if ($adeudado === 0) {
            throw new InvalidArgumentException(_("Esta persona no tiene deuda de vivienda pendiente"));
        }
        $ini = strtolower($persona->iniciales);
        $medido = $this->medir->ejecutar($fecha);
        $bruto = $medido[$ini]['superavit'] ?? Dinero::zero();
        $aplicado = $saldoSuper < 0 ? -$saldoSuper : 0;
        $disponible = $bruto->toCents() - $aplicado;
        $partes = ImporteSaldableDeuda::partes($saldoDeuda, $disponible, $saldoCc);
        if ($partes['total'] <= 0) {
            throw new InvalidArgumentException(_("No hay superávit de vivienda ni saldo contable positivo. El superávit es lo ingresado en vivienda por encima de la cuota; si no alcanza, anota antes el ingreso."));
        }

        $glosa = sprintf('Liquidación deuda de vivienda %s', $ini);
        if ($partes['superavit'] > 0) {
            [$p, $g] = ConstructorAsientosLiquidacionDeuda::construir(
                $contexto->ejercicioId,
                $persona->id,
                $fecha,
                $deuda->id,
                $superP->id,
                $deudores->id,
                $superG->id,
                $partes['superavit'],
                sprintf('Superávit de vivienda %s', $ini),
                CuentasDeudaVivienda::TIPO_SUPERAVIT,
            );
            $this->asientos->guardarEnlazados($p, $g);
        }
        if ($partes['saldo'] > 0) {
            [$p, $g] = ConstructorAsientosLiquidacionDeuda::construir(
                $contexto->ejercicioId,
                $persona->id,
                $fecha,
                $deuda->id,
                $cc->id,
                $deudores->id,
                $regG->id,
                $partes['saldo'],
                $glosa,
            );
            $this->asientos->guardarEnlazados($p, $g);
        }
        $restante = $adeudado - $partes['total'];

        return [
            'iniciales' => $persona->iniciales,
            'saldado' => Dinero::fromCents($partes['total'])->toString(),
            'saldado_es' => Dinero::fromCents($partes['total'])->formatEs(),
            'desde_superavit_es' => Dinero::fromCents($partes['superavit'])->formatEs(),
            'desde_saldo_es' => Dinero::fromCents($partes['saldo'])->formatEs(),
            'deuda_restante' => Dinero::fromCents(-$restante)->toString(),
            'deuda_restante_es' => Dinero::fromCents(-$restante)->formatEs(),
        ];
    }

    private function saldoCuenta(int $centroId, int $ejercicioId, string $desde, string $hasta, int $cuentaId): int
    {
        foreach ($this->asientos->saldosPorCuenta($centroId, $ejercicioId, $desde, $hasta) as $row) {
            if ((int) $row['id'] === $cuentaId) {
                return (int) $row['saldo_cents'];
            }
        }

        return 0;
    }

    private function deudores(int $centroId): Cuenta
    {
        $cuenta = $this->cuentas->deudoresVivienda($centroId);
        if ($cuenta === null) {
            throw new InvalidArgumentException(_("Cuenta DEUDORES.VIV no encontrada"));
        }

        return $cuenta;
    }
}
