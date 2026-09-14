<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;

final class CambiarPasswordUsuario
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(int $identidadId, string $actual, string $nueva, string $confirmacion): void
    {
        $actual = trim($actual);
        $nueva = trim($nueva);
        $confirmacion = trim($confirmacion);
        if ($actual === '') {
            throw new InvalidArgumentException('Indique la contraseña actual');
        }
        if (strlen($nueva) < 6) {
            throw new InvalidArgumentException('La contraseña nueva debe tener al menos 6 caracteres');
        }
        if ($nueva !== $confirmacion) {
            throw new InvalidArgumentException('Las contraseñas nuevas no coinciden');
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null) {
            throw new InvalidArgumentException('Sesión caducada');
        }
        if (!password_verify($actual, $identidad->passwordHash)) {
            throw new InvalidArgumentException('La contraseña actual no es correcta');
        }
        $this->identidades->guardar(new Identidad(
            $identidad->id,
            $identidad->email,
            password_hash($nueva, PASSWORD_DEFAULT),
            $identidad->nombre,
            $identidad->activo,
            $identidad->intentosFallidos,
            $identidad->bloqueadoHasta,
            $identidad->ultimoAcceso,
            $identidad->alias,
        ));
    }
}
