<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;

/** Crea o reutiliza una identidad de centro y la vincula a un centro concreto. */
final class AsegurarIdentidadCentro
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(
        int $centroId,
        string $alias,
        string $email,
        string $password,
        string $nombre = '',
        string $rol = 'admin',
    ): Identidad {
        $alias = strtolower(trim($alias));
        $email = strtolower(trim($email));
        $password = trim($password);
        $nombre = trim($nombre);
        if ($alias === '' || $email === '') {
            throw new InvalidArgumentException('Usuario (alias) y correo son obligatorios');
        }
        if (preg_match('/^[a-z][a-z0-9._-]{1,31}$/', $alias) !== 1) {
            throw new InvalidArgumentException(
                'El usuario debe empezar por letra y tener 2-32 caracteres (letras, números, punto, guion)'
            );
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El correo no es válido');
        }

        $porAlias = $this->identidades->porEmailOAlias($alias);
        $porEmail = $this->identidades->porEmailOAlias($email);
        if ($porAlias !== null && $porEmail !== null && $porAlias->id !== $porEmail->id) {
            throw new InvalidArgumentException('El usuario y el correo pertenecen a cuentas distintas');
        }
        $identidad = $porAlias ?? $porEmail;

        if ($identidad !== null && $identidad->id !== null) {
            if (strtolower($identidad->email) !== $email) {
                throw new InvalidArgumentException(
                    'El usuario «' . $alias . '» ya existe con otro correo'
                );
            }
            if (
                $identidad->alias !== null
                && strtolower($identidad->alias) !== $alias
            ) {
                throw new InvalidArgumentException(
                    'Ese correo ya existe con el usuario «' . $identidad->alias . '»'
                );
            }
            if (
                $this->identidades->centrosDe($identidad->id) === []
                && $this->identidades->personasDe($identidad->id) !== []
            ) {
                throw new InvalidArgumentException(
                    'Ese correo o usuario ya es una cuenta personal; no puede ser secretario de un centro'
                );
            }
            if ($password !== '') {
                if (strlen($password) < 6) {
                    throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres');
                }
                $identidad = $this->identidades->guardar(new Identidad(
                    $identidad->id,
                    $identidad->email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $nombre !== '' ? $nombre : $identidad->nombre,
                    $identidad->activo,
                    $identidad->intentosFallidos,
                    $identidad->bloqueadoHasta,
                    $identidad->ultimoAcceso,
                    $identidad->alias ?? $alias,
                ));
            }
            if ($identidad->id === null) {
                throw new InvalidArgumentException('No se pudo actualizar el usuario');
            }
            $this->identidades->vincularCentro($identidad->id, $centroId, $rol);

            return $identidad;
        }

        if ($password === '' || strlen($password) < 6) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres');
        }
        $creada = $this->identidades->guardar(new Identidad(
            null,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $nombre !== '' ? $nombre : $alias,
            true,
            0,
            null,
            null,
            $alias,
        ));
        if ($creada->id === null) {
            throw new InvalidArgumentException('No se pudo crear el usuario');
        }
        $this->identidades->vincularCentro($creada->id, $centroId, $rol);

        return $creada;
    }
}
