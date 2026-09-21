<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\services\GeneradorTokenVerificacion;
use src\ambito\application\CrearCentro;
use src\ambito\domain\entity\Centro;

/**
 * Alta pública de un centro nuevo con su secretario (nivel 2).
 * El correo debe confirmarse antes de entrar.
 */
final class RegistrarCentro
{
    public function __construct(
        private readonly CrearCentro $crearCentro,
        private readonly IdentidadRepository $identidades,
    ) {
    }

    /**
     * @return array{identidad: Identidad, centro: Centro, token_verificacion: string}
     */
    public function ejecutar(
        string $codigo,
        string $nombreCentro,
        string $tipo,
        string $alias,
        string $email,
        string $password,
        string $passwordConfirm,
        string $nombre = '',
        bool $aceptoCondiciones = false,
    ): array {
        $codigo = trim($codigo);
        $nombreCentro = trim($nombreCentro);
        $tipo = strtolower(trim($tipo));
        $alias = strtolower(trim($alias));
        $email = strtolower(trim($email));
        $password = trim($password);
        $passwordConfirm = trim($passwordConfirm);
        $nombre = trim($nombre);
        if ($codigo === '' || $nombreCentro === '') {
            throw new InvalidArgumentException(_("Código y nombre del centro son obligatorios"));
        }
        if (!in_array($tipo, ['n', 'sg'], true)) {
            throw new InvalidArgumentException(_("Indique el tipo de centro: n o sg"));
        }
        if ($alias === '' || $email === '') {
            throw new InvalidArgumentException(_("Usuario y correo del secretario son obligatorios"));
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
        if ($this->identidades->porEmailOAlias($alias) !== null) {
            throw new InvalidArgumentException(_("Ese usuario ya existe"));
        }
        if ($this->identidades->porEmailOAlias($email) !== null) {
            throw new InvalidArgumentException(_("Ese correo ya tiene una cuenta"));
        }
        if (!$aceptoCondiciones) {
            throw new InvalidArgumentException(_("Debe aceptar las Condiciones de uso"));
        }

        $anio = (int) (new DateTimeImmutable())->format('Y');
        $alta = $this->crearCentro->ejecutar([
            'codigo' => $codigo,
            'nombre' => $nombreCentro,
            'tipo' => $tipo,
            'usuario' => $alias,
            'email' => $email,
            'password' => $password,
            'nombre_usuario' => $nombre !== '' ? $nombre : $alias,
            'fecha_inicio' => sprintf('%d-01-01', $anio),
            'fecha_fin' => sprintf('%d-12-31', $anio),
            'verificar_email' => false,
        ]);
        $identidad = $alta['identidad'];
        if ($identidad->id === null) {
            throw new InvalidArgumentException(_("No se pudo crear la cuenta del secretario"));
        }

        $token = GeneradorTokenVerificacion::generar();
        $this->identidades->guardarVerificacionEmail(
            $identidad->id,
            $token,
            (new DateTimeImmutable())->modify('+48 hours'),
        );

        return [
            'identidad' => $this->identidades->porId($identidad->id) ?? $identidad,
            'centro' => $alta['centro'],
            'token_verificacion' => $token,
        ];
    }
}
