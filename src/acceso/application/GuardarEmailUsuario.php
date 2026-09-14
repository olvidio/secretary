<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\personas\domain\contracts\PersonaRepository;

final class GuardarEmailUsuario
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
            throw new InvalidArgumentException('El correo no es válido');
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null) {
            throw new InvalidArgumentException('Sesión caducada');
        }
        $otro = $this->identidades->porEmailOAlias($email);
        if ($otro !== null && $otro->id !== $identidadId) {
            throw new InvalidArgumentException('Ese correo ya tiene una cuenta');
        }
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
        ));
        foreach ($this->identidades->personasDe($identidadId) as $personaId) {
            $this->personas->guardarEmail($personaId, $email);
        }

        return $email;
    }
}
