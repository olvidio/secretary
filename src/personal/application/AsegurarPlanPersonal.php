<?php

declare(strict_types=1);

namespace src\personal\application;

use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\personal\domain\services\CatalogoMaestroPersonal;

final class AsegurarPlanPersonal
{
    public function __construct(private readonly CuentaRepository $cuentas)
    {
    }

    public function ejecutar(int $centroId, int $personaId): void
    {
        foreach (CatalogoMaestroPersonal::cuentas() as $c) {
            if ($this->cuentas->buscar($centroId, $personaId, 'X', $c['codigo']) !== null) {
                continue;
            }
            $this->cuentas->guardar(new Cuenta(
                null,
                $centroId,
                $personaId,
                null,
                null,
                'X',
                $c['codigo'],
                $c['nombre'],
                $c['descripcion'],
                $c['tipo'],
                $c['naturaleza'],
                $c['codigo'],
                true,
                $c['orden'],
            ));
        }
        $this->asegurarTesoreria($centroId, $personaId, 'CAJA', 'Caja', 1);
        $this->asegurarTesoreria($centroId, $personaId, 'BANCO', 'Banco', 2);
        if ($this->cuentas->buscar($centroId, $personaId, 'X', 'PUENTE.PERIODIFICACION') === null) {
            $this->cuentas->guardar(new Cuenta(
                null,
                $centroId,
                $personaId,
                null,
                null,
                'X',
                'PUENTE.PERIODIFICACION',
                'Periodificación',
                'Contrapartida de imputación a período distinto de la tesorería (D13)',
                'puente',
                'deudora',
                'PERIODIFICACION',
                true,
                999,
            ));
        }
    }

    private function asegurarTesoreria(int $centroId, int $personaId, string $maestro, string $nombre, int $orden): void
    {
        if ($this->cuentas->buscar($centroId, $personaId, 'X', $maestro) !== null) {
            return;
        }
        $this->cuentas->guardar(new Cuenta(
            null,
            $centroId,
            $personaId,
            null,
            null,
            'X',
            $maestro,
            $nombre,
            'Tesorería propia del nivel 1 (no es la caja/banco del centro)',
            'tesoreria',
            'deudora',
            $maestro,
            true,
            $orden,
        ));
    }
}
