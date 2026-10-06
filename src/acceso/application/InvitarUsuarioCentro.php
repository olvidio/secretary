<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\value_objects\RolCentro;

/**
 * Otorga mandato (identidad_centro) a una persona: reutiliza identidad por correo o crea una nueva (D15).
 */
final class InvitarUsuarioCentro
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly QuedaEscritorCentro $quedaEscritor,
    ) {
    }

    public function ejecutar(
        int $centroId,
        string $email,
        string $alias = '',
        string $password = '',
        string $nombre = '',
        string $rol = RolCentro::ADMIN,
        bool $verificarEmail = true,
    ): Identidad {
        $rol = RolCentro::exigirAsignable($rol);
        $email = strtolower(trim($email));
        $alias = strtolower(trim($alias));
        $password = trim($password);
        $nombre = trim($nombre);
        if ($email === '') {
            throw new InvalidArgumentException(_("El correo es obligatorio"));
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(_("El correo no es válido"));
        }

        $porEmail = $this->identidadesActivasPorEmail($email);
        if (count($porEmail) > 1) {
            throw new InvalidArgumentException(
                _('Este correo tiene varias cuentas activas (convención antigua). Entre con el alias concreto o pida al administrador que unifique cuentas.')
            );
        }

        if (count($porEmail) === 1) {
            return $this->otorgarMandato(
                $centroId,
                $porEmail[0],
                $alias,
                $password,
                $nombre,
                $rol,
            );
        }

        if ($alias === '') {
            throw new InvalidArgumentException(
                _('No hay cuenta con ese correo. Indique alias y contraseña para crear una identidad nueva.')
            );
        }
        if (preg_match('/^[a-z][a-z0-9._-]{1,31}$/', $alias) !== 1) {
            throw new InvalidArgumentException(_("El alias debe empezar por letra y tener 2-32 caracteres (letras, números, punto, guion)"));
        }
        $porAlias = $this->identidades->porAlias($alias);
        if ($porAlias !== null) {
            if (strtolower($porAlias->email) !== $email) {
                throw new InvalidArgumentException(sprintf(_("El alias «%s» ya existe con otro correo"), $alias));
            }
            return $this->otorgarMandato($centroId, $porAlias, '', $password, $nombre, $rol);
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

    private function otorgarMandato(
        int $centroId,
        Identidad $identidad,
        string $aliasForm,
        string $password,
        string $nombre,
        string $rol,
    ): Identidad {
        if ($identidad->id === null) {
            throw new InvalidArgumentException(_("Cuenta no válida"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("El administrador de plataforma no se vincula como secretario de centro"));
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
                $identidad->alias,
                $identidad->emailVerificadoAt,
            ));
        } elseif ($nombre !== '' && $nombre !== $identidad->nombre) {
            $identidad = $this->identidades->guardar(new Identidad(
                $identidad->id,
                $identidad->email,
                $identidad->passwordHash,
                $nombre,
                $identidad->activo,
                $identidad->intentosFallidos,
                $identidad->bloqueadoHasta,
                $identidad->ultimoAcceso,
                $identidad->alias,
                $identidad->emailVerificadoAt,
            ));
        }
        $this->exigirSiDejaDeEscribir($centroId, $identidad->id, $rol);
        $this->identidades->vincularCentro($identidad->id, $centroId, $rol);

        return $identidad;
    }

    /** @return list<Identidad> */
    private function identidadesActivasPorEmail(string $email): array
    {
        $out = [];
        foreach ($this->identidades->listarPorEmail($email) as $identidad) {
            if ($identidad->id !== null && $identidad->activo) {
                $out[] = $identidad;
            }
        }

        return $out;
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
