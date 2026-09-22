<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\personas\domain\contracts\PersonaRepository;

/** Fija el correo de la identidad y lo replica en sus personas vinculadas. */
final class AplicarEmailIdentidad
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
    ) {
    }

    public function ejecutar(int $identidadId, string $email): string
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(_("El correo no es válido"));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null) {
            throw new InvalidArgumentException(_("Sesión caducada"));
        }
        if ($email === strtolower(trim($identidad->email))) {
            return $email;
        }
        $this->comprobarDisponible($identidadId, $email);
        $this->identidades->guardar(new Identidad(
            $identidad->id,
            $email,
            $identidad->passwordHash,
            $identidad->nombre,
            $identidad->activo,
            $identidad->intentosFallidos,
            $identidad->bloqueadoHasta,
            $identidad->ultimoAcceso,
            $identidad->alias,
            $identidad->emailVerificadoAt,
        ));
        foreach ($this->identidades->personasDe($identidadId) as $personaId) {
            $this->personas->guardarEmail($personaId, $email);
        }

        return $email;
    }

    public function comprobarDisponible(int $identidadId, string $email): void
    {
        if (!$this->identidades->esCuentaPersonal($identidadId)) {
            return;
        }
        $otra = $this->identidades->cuentaPersonalPorEmail($email);
        if ($otra !== null && $otra->id !== $identidadId) {
            throw new InvalidArgumentException(_("Ese correo ya tiene una cuenta personal"));
        }
    }
}
