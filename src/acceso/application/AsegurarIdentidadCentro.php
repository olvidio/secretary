<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\value_objects\RolCentro;

/** Crea o reutiliza una identidad de centro y la vincula a un centro concreto. */
final class AsegurarIdentidadCentro
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly QuedaEscritorCentro $quedaEscritor,
    ) {
    }

    public function ejecutar(
        int $centroId,
        string $alias,
        string $email,
        string $password,
        string $nombre = '',
        string $rol = RolCentro::ADMIN,
        bool $verificarEmail = true,
    ): Identidad {
        $rol = RolCentro::exigirAsignable($rol);
        $alias = strtolower(trim($alias));
        $email = strtolower(trim($email));
        $password = trim($password);
        $nombre = trim($nombre);
        if ($alias === '' || $email === '') {
            throw new InvalidArgumentException(_("Alias y correo son obligatorios"));
        }
        if (preg_match('/^[a-z][a-z0-9._-]{1,31}$/', $alias) !== 1) {
            throw new InvalidArgumentException(_("El alias debe empezar por letra y tener 2-32 caracteres (letras, números, punto, guion)"));
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(_("El correo no es válido"));
        }

        $identidad = $this->identidades->porAlias($alias);

        if ($identidad !== null && $identidad->id !== null) {
            if (strtolower($identidad->email) !== $email) {
                throw new InvalidArgumentException(sprintf(_("El alias «%s» ya existe con otro correo"), $alias));
            }
            if ($password !== '') {
                if (strlen($password) < 6) {
                    throw new InvalidArgumentException(_("La contraseña debe tener al menos 6 caracteres"));
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
                    $identidad->emailVerificadoAt,
                ));
            }
            if ($identidad->id === null) {
                throw new InvalidArgumentException(_("No se pudo actualizar el usuario"));
            }
            $this->exigirSiDejaDeEscribir($centroId, $identidad->id, $rol);
            $this->identidades->vincularCentro($identidad->id, $centroId, $rol);

            return $identidad;
        }

        if ($password === '' || strlen($password) < 6) {
            throw new InvalidArgumentException(_("La contraseña debe tener al menos 6 caracteres"));
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
            throw new InvalidArgumentException(_("No se pudo crear el usuario"));
        }
        if ($verificarEmail) {
            $this->identidades->marcarEmailVerificado($creada->id, new DateTimeImmutable());
        }
        $this->exigirSiDejaDeEscribir($centroId, $creada->id, $rol);
        $this->identidades->vincularCentro($creada->id, $centroId, $rol);

        return $this->identidades->porId($creada->id) ?? $creada;
    }

    private function exigirSiDejaDeEscribir(int $centroId, int $identidadId, string $rol): void
    {
        if (RolCentro::puedeEscribir($rol)) {
            return;
        }
        $actual = $this->identidades->rolEnCentro($identidadId, $centroId);
        if ($actual !== null && RolCentro::puedeEscribir($actual)) {
            $this->quedaEscritor->comprobar($centroId, $identidadId);
        }
    }
}
