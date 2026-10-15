<?php

declare(strict_types=1);

namespace src\ambito\application;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\cierre\domain\services\CuentasDeudaVivienda;
use src\personas\domain\entity\Persona;

/** Cuenta P `DEUDA.VIV.<INICIALES>` y regularización P/G del centro. */
final class AsegurarCuentaDeudaVivienda
{
    public function __construct(private readonly CuentaRepository $cuentas)
    {
    }

    public function ejecutar(Persona $persona): Cuenta
    {
        if ($persona->id === null || $persona->centroId === null) {
            throw new InvalidArgumentException(_("La persona no tiene centro"));
        }
        $this->asegurarRegularizacion($persona->centroId);
        $this->asegurarSuperavitColectivo($persona->centroId);
        $this->asegurarSuperavitPersona($persona);
        $existente = $this->cuentas->deudaViviendaDe($persona->centroId, $persona->id);
        if ($existente !== null) {
            return $existente;
        }
        $iniciales = strtoupper($persona->iniciales);
        $nombre = $persona->nombreCompleto();

        return $this->cuentas->guardar(new Cuenta(
            null,
            $persona->centroId,
            $persona->id,
            null,
            null,
            'P',
            CuentasDeudaVivienda::codigoPersona($iniciales),
            'Deuda de vivienda de ' . $nombre,
            'Vivienda imputada en el cierre, pendiente de liquidar. No es el saldo contable (CC) ni trabajo (111).',
            'personal',
            'acreedora',
            CuentasDeudaVivienda::MAESTRO,
            true,
            2,
        ));
    }

    public function regularizacion(int $centroId, string $libro): Cuenta
    {
        $this->asegurarRegularizacion($centroId);
        $cuenta = $this->cuentas->regularizacionDeuda($centroId, $libro);
        if ($cuenta === null || $cuenta->id === null) {
            throw new InvalidArgumentException(sprintf(_("Falta la regularización de deuda de vivienda en el libro %s"), $libro));
        }

        return $cuenta;
    }

    public function superavitDe(Persona $persona): Cuenta
    {
        if ($persona->id === null || $persona->centroId === null) {
            throw new InvalidArgumentException(_("La persona no tiene centro"));
        }
        $this->asegurarSuperavitColectivo($persona->centroId);

        return $this->asegurarSuperavitPersona($persona);
    }

    public function superavitColectivo(int $centroId): Cuenta
    {
        return $this->asegurarSuperavitColectivo($centroId);
    }

    private function asegurarSuperavitPersona(Persona $persona): Cuenta
    {
        if ($persona->id === null || $persona->centroId === null) {
            throw new InvalidArgumentException(_("La persona no tiene centro"));
        }
        $existente = $this->cuentas->superavitViviendaDe($persona->centroId, $persona->id);
        if ($existente !== null) {
            return $existente;
        }
        $iniciales = strtoupper($persona->iniciales);
        $nombre = $persona->nombreCompleto();

        return $this->cuentas->guardar(new Cuenta(
            null,
            $persona->centroId,
            $persona->id,
            null,
            null,
            'P',
            CuentasDeudaVivienda::codigoSuperavit($iniciales),
            'Superávit de vivienda de ' . $nombre,
            'Exceso de vivienda ya ingresado sobre la cuota. Sirve para saldar la deuda del cierre, sin el saldo contable ni un 211 nuevo.',
            'puente',
            'acreedora',
            CuentasDeudaVivienda::MAESTRO_SUPERAVIT,
            true,
            3,
        ));
    }

    private function asegurarSuperavitColectivo(int $centroId): Cuenta
    {
        $existente = $this->cuentas->superavitVivienda($centroId);
        if ($existente !== null) {
            return $existente;
        }

        return $this->cuentas->guardar(new Cuenta(
            null,
            $centroId,
            null,
            null,
            null,
            'G',
            CuentasDeudaVivienda::SUPERAVIT,
            'Superávit de vivienda (personas)',
            'Contrapartida en G al saldar deuda de vivienda con el exceso ya ingresado. No es ingreso del 613.',
            'puente',
            'deudora',
            CuentasDeudaVivienda::MAESTRO_SUPERAVIT_G,
            true,
            998,
        ));
    }

    private function asegurarRegularizacion(int $centroId): void
    {
        foreach (['P', 'G'] as $libro) {
            if ($this->cuentas->regularizacionDeuda($centroId, $libro) !== null) {
                continue;
            }
            $this->cuentas->guardar(new Cuenta(
                null,
                $centroId,
                null,
                null,
                null,
                $libro,
                CuentasDeudaVivienda::REG,
                'Regularización deuda de vivienda',
                'Contrapartida al saldar deuda de vivienda. No es ingreso ni gasto del 613.',
                'puente',
                'deudora',
                CuentasDeudaVivienda::MAESTRO_REG,
                true,
                997,
            ));
        }
    }
}
