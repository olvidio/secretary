<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\contracts\LibroPersonalIdentidadPort;
use src\acceso\domain\entity\Identidad;
use src\personas\domain\contracts\PersonaRepository;

/**
 * Alta pública desde el login: identidad de persona (nivel 1) sin centro.
 * El vínculo a un centro se solicita después desde /yo/centros.
 */
final class RegistrarUsuario
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
        private readonly LibroPersonalIdentidadPort $libroPersonal,
    ) {
    }

    /**
     * @return array{identidad: Identidad, token_verificacion: string, enviar_correo: bool}
     */
    public function ejecutar(
        string $alias,
        string $email,
        string $password,
        string $passwordConfirm,
        string $nombre = '',
        bool $aceptoCondiciones = false,
    ): array {
        $alias = strtolower(trim($alias));
        $email = strtolower(trim($email));
        $password = trim($password);
        $passwordConfirm = trim($passwordConfirm);
        $nombre = trim($nombre);
        if ($alias === '' || $email === '') {
            throw new InvalidArgumentException(_("Usuario y correo son obligatorios"));
        }
        if (preg_match('/^[a-z][a-z0-9._-]{1,31}$/', $alias) !== 1) {
            throw new InvalidArgumentException(_("El usuario debe empezar por letra y tener 2-32 caracteres (letras, números, punto, guion)"));
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(_("El correo no es válido"));
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException(_("La contraseña debe tener al menos 6 caracteres"));
        }
        if ($password !== $passwordConfirm) {
            throw new InvalidArgumentException(_("Las contraseñas no coinciden"));
        }
        if ($this->identidades->porAlias($alias) !== null) {
            throw new InvalidArgumentException(_("Ese usuario ya existe"));
        }
        if ($this->identidades->cuentaPersonalPorEmail($email) !== null) {
            throw new InvalidArgumentException(_("Ese correo ya tiene una cuenta personal"));
        }
        if ($this->personas->porEmail($email) !== null) {
            throw new InvalidArgumentException(_("Ese correo ya está asignado a un nombre"));
        }
        if ($nombre === '') {
            $nombre = $alias;
        }
        if (!$aceptoCondiciones) {
            throw new InvalidArgumentException(_("Debe aceptar las Condiciones de uso"));
        }

        $creada = $this->identidades->guardar(new Identidad(
            null,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $nombre,
            true,
            0,
            null,
            null,
            $alias,
        ));
        if ($creada->id === null) {
            throw new InvalidArgumentException(_("No se pudo crear la cuenta"));
        }

        $verificacion = PoliticaVerificacionEmailRegistro::prepararTrasAlta($this->identidades, $creada->id);
        $this->libroPersonal->ejecutar($creada->id);

        return [
            'identidad' => $this->identidades->porId($creada->id) ?? $creada,
            'token_verificacion' => $verificacion['token'],
            'enviar_correo' => $verificacion['enviar_correo'],
        ];
    }
}
