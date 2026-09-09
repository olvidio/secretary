<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;

/**
 * El correo de un nombre del centro es el login del libro personal.
 * Si el correo es nuevo, se genera una contraseña inicial (una sola vez).
 */
final class VincularEmailPersona
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
    ) {
    }

    public function ejecutar(Persona $persona, string $email): ?string
    {
        if ($persona->id === null) {
            throw new InvalidArgumentException('La persona debe estar guardada');
        }
        $email = strtolower(trim($email));
        if ($email === '') {
            $this->identidades->desvincularPersona($persona->id);
            $this->personas->guardarEmail($persona->id, null);

            return null;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El correo no es válido');
        }

        $otra = $this->personas->porEmail($email);
        if ($otra !== null && $otra->id !== $persona->id) {
            throw new InvalidArgumentException('Ese correo ya está asignado a otro nombre');
        }

        $actual = $this->identidades->identidadDePersona($persona->id);
        if ($actual !== null && $actual->id !== null) {
            if (strtolower($actual->email) !== $email) {
                $conflicto = $this->identidades->porEmailOAlias($email);
                if ($conflicto !== null && $conflicto->id !== $actual->id) {
                    throw new InvalidArgumentException('Ese correo ya tiene una cuenta');
                }
                $this->identidades->guardar(new Identidad(
                    $actual->id,
                    $email,
                    $actual->passwordHash,
                    $persona->nombreCompleto() !== '' ? $persona->nombreCompleto() : $actual->nombre,
                    $actual->activo,
                    $actual->intentosFallidos,
                    $actual->bloqueadoHasta,
                    $actual->ultimoAcceso,
                    $actual->alias,
                ));
            }
            $this->personas->guardarEmail($persona->id, $email);

            return null;
        }

        $existente = $this->identidades->porEmailOAlias($email);
        if ($existente !== null && $existente->id !== null) {
            if ($this->identidades->centrosDe($existente->id) !== []) {
                throw new InvalidArgumentException(
                    'Ese correo es de un usuario de centro; no puede usarse como cuenta personal'
                );
            }
            $otras = $this->identidades->personasDe($existente->id);
            foreach ($otras as $pid) {
                if ($pid !== $persona->id) {
                    throw new InvalidArgumentException('Ese correo ya está vinculado a otra persona');
                }
            }
            $this->identidades->vincularPersona($existente->id, $persona->id);
            $this->personas->guardarEmail($persona->id, $email);

            return null;
        }

        $password = self::passwordInicial();
        $creada = $this->identidades->guardar(new Identidad(
            null,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $persona->nombreCompleto() !== '' ? $persona->nombreCompleto() : $email,
            true,
            0,
            null,
            null,
            null,
        ));
        if ($creada->id === null) {
            throw new InvalidArgumentException('No se pudo crear la cuenta personal');
        }
        $this->identidades->vincularPersona($creada->id, $persona->id);
        $this->personas->guardarEmail($persona->id, $email);

        return $password;
    }

    private static function passwordInicial(): string
    {
        return bin2hex(random_bytes(5));
    }
}
