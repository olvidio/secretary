<?php

declare(strict_types=1);

namespace src\ambito\application;

use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\personas\domain\entity\Persona;

/** Cuenta corriente P (`CC.<INICIALES>`) de una persona del centro. */
final class AsegurarCuentaCorrientePersona
{
    public function __construct(private readonly CuentaRepository $cuentas)
    {
    }

    public function ejecutar(Persona $persona): void
    {
        if ($persona->id === null || $persona->centroId === null) {
            return;
        }
        if ($this->cuentas->personalDe($persona->centroId, $persona->id) !== null) {
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
            'CC.' . $iniciales,
            'Cuenta personal de ' . $persona->nombreCompleto(),
            'Cuenta personal (c/c) de ' . $persona->nombreCompleto() . ' dentro del libro P',
            'personal',
            'deudora',
            '9',
            true,
            0,
        ));
    }
}
