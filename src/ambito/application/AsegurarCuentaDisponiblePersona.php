<?php

declare(strict_types=1);

namespace src\ambito\application;

use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\personas\domain\entity\Persona;

/** Cuenta de aparcamiento P (`DISP.<INICIALES>`) del sobrante de remesa. */
final class AsegurarCuentaDisponiblePersona
{
    public function __construct(private readonly CuentaRepository $cuentas)
    {
    }

    public function ejecutar(Persona $persona): void
    {
        if ($persona->id === null || $persona->centroId === null) {
            return;
        }
        if ($this->cuentas->disponibleDe($persona->centroId, $persona->id) !== null) {
            return;
        }
        $iniciales = strtoupper($persona->iniciales);
        $this->cuentas->guardar(new Cuenta(
            null,
            $persona->centroId,
            $persona->id,
            null,
            null,
            'P',
            'DISP.' . $iniciales,
            'Disponible pendiente de labores de ' . $persona->nombreCompleto(),
            'Aparcamiento del sobrante de remesa hasta asignarlo a partidas 7',
            'personal',
            'deudora',
            'DISP',
            true,
            1,
        ));
    }
}
