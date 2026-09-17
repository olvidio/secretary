<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use src\acceso\domain\contracts\IdentidadRepository;

final class IniciarSesion
{
    private const HASH_FALSO = '$2y$10$usesomesillystringfore7hnbRJHxMlgAzqm/YiwKgm.nN4jSPHi';

    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly ResolverPersonaActiva $resolverPersona,
    ) {
    }

    public function ejecutar(string $identificador, string $password, ?DateTimeImmutable $ahora = null): ResultadoLogin
    {
        $ahora ??= new DateTimeImmutable();
        $identificador = trim($identificador);
        if ($identificador === '') {
            return new ResultadoLogin('fallo', _("Usuario o contraseña incorrectos"));
        }
        $identidad = $this->identidades->porEmailOAlias($identificador);
        $hash = $identidad !== null ? $identidad->passwordHash : self::HASH_FALSO;
        $passwordOk = password_verify($password, $hash);
        if ($identidad === null || $identidad->id === null) {
            return new ResultadoLogin(
                'desconocido',
                _("No hay cuenta con ese usuario. Puede registrarse."),
            );
        }
        if (!$identidad->activo) {
            return new ResultadoLogin('fallo', _("Usuario o contraseña incorrectos"));
        }
        if ($identidad->estaBloqueada($ahora)) {
            return new ResultadoLogin('fallo', _("Cuenta temporalmente bloqueada. Pruebe más tarde."));
        }
        if (!$passwordOk) {
            $this->identidades->registrarFallo($identidad, $ahora);

            return new ResultadoLogin('fallo', _("Usuario o contraseña incorrectos"));
        }
        if (!$this->identidades->emailVerificado($identidad->id)) {
            return new ResultadoLogin(
                'fallo',
                _("Confirme su correo antes de entrar. Revise su bandeja o solicite un nuevo enlace desde el registro."),
            );
        }

        $centros = [];
        foreach ($this->identidades->centrosDe($identidad->id) as $v) {
            $centros[] = [
                'centro_id' => $v->centroId,
                'codigo' => $v->codigo,
                'nombre' => $v->nombre,
                'rol' => $v->rol,
            ];
        }
        $personas = $this->identidades->personasDe($identidad->id);
        if ($centros === [] && $personas === []) {
            return new ResultadoLogin('fallo', _("Usuario o contraseña incorrectos"));
        }
        $this->identidades->registrarExito($identidad, $ahora);
        $totpOk = $this->identidades->totpConfirmado($identidad->id);

        if ($centros !== []) {
            $estado = $totpOk ? 'pendiente_verificar' : 'pendiente_activar';

            return new ResultadoLogin(
                $estado,
                '',
                $identidad->id,
                $identidad->nombre,
                $identidad->email,
                'centro',
                $centros,
            );
        }

        $personaId = $this->resolverPersona->ejecutar($identidad->id, null)['persona_id'];
        if ($totpOk) {
            return new ResultadoLogin(
                'pendiente_verificar',
                '',
                $identidad->id,
                $identidad->nombre,
                $identidad->email,
                'persona',
                [],
                $personaId,
            );
        }

        return new ResultadoLogin(
            'autenticado',
            '',
            $identidad->id,
            $identidad->nombre,
            $identidad->email,
            'persona',
            [],
            $personaId,
        );
    }
}
