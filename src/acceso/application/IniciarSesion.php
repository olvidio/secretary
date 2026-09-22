<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\contracts\LibroPersonalIdentidadPort;
use src\acceso\domain\entity\Identidad;

final class IniciarSesion
{
    private const HASH_FALSO = '$2y$10$usesomesillystringfore7hnbRJHxMlgAzqm/YiwKgm.nN4jSPHi';

    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly ResolverPersonaActiva $resolverPersona,
        private readonly LibroPersonalIdentidadPort $libroPersonal,
        private readonly EtiquetaCuentaIdentidad $etiquetaCuenta,
    ) {
    }

    public function ejecutar(string $identificador, string $password, ?DateTimeImmutable $ahora = null): ResultadoLogin
    {
        $ahora ??= new DateTimeImmutable();
        $identificador = trim($identificador);
        if ($identificador === '') {
            return new ResultadoLogin('fallo', _("Usuario o contraseña incorrectos"));
        }

        $candidatas = $this->candidatasDe($identificador);
        if ($candidatas === []) {
            password_verify($password, self::HASH_FALSO);

            return new ResultadoLogin(
                'desconocido',
                _("No hay cuenta con ese usuario. Puede registrarse."),
            );
        }

        $validas = [];
        foreach ($candidatas as $identidad) {
            if ($identidad->id === null) {
                continue;
            }
            if (!$identidad->activo) {
                if (
                    count($candidatas) === 1
                    && password_verify($password, $identidad->passwordHash)
                ) {
                    $baja = $this->identidades->bajaCentroPendiente($identidad->id);
                    if ($baja !== null) {
                        return new ResultadoLogin(
                            'fallo',
                            sprintf(
                                _('Cuenta en baja programada. Purga prevista el %s. Contacte con el administrador para reactivarla.'),
                                $baja['ejecutar']->format('Y-m-d'),
                            ),
                        );
                    }
                }
                continue;
            }
            if ($identidad->estaBloqueada($ahora)) {
                continue;
            }
            if (password_verify($password, $identidad->passwordHash)) {
                $validas[] = $identidad;
            }
        }

        if ($validas === []) {
            if (count($candidatas) === 1) {
                $unica = $candidatas[0];
                if ($unica->estaBloqueada($ahora)) {
                    return new ResultadoLogin(
                        'fallo',
                        _("Cuenta temporalmente bloqueada. Pruebe más tarde."),
                    );
                }
                $this->identidades->registrarFallo($unica, $ahora);
            }

            return new ResultadoLogin('fallo', _("Usuario o contraseña incorrectos"));
        }

        if (count($validas) > 1) {
            $cuentas = [];
            foreach ($validas as $identidad) {
                if ($identidad->id === null) {
                    continue;
                }
                $cuentas[] = [
                    'identidad_id' => $identidad->id,
                    'etiqueta' => $this->etiquetaCuenta->ejecutar($identidad),
                ];
            }

            return new ResultadoLogin(
                'pendiente_elegir_cuenta',
                '',
                null,
                '',
                strtolower(trim($identificador)),
                '',
                [],
                null,
                $cuentas,
            );
        }

        return $this->continuarConIdentidad($validas[0], $ahora);
    }

    public function continuarConIdentidad(Identidad $identidad, ?DateTimeImmutable $ahora = null): ResultadoLogin
    {
        $ahora ??= new DateTimeImmutable();
        if ($identidad->id === null) {
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
        $this->identidades->registrarExito($identidad, $ahora);
        if ($identidad->esAdmin) {
            return new ResultadoLogin(
                'autenticado',
                '',
                $identidad->id,
                $identidad->nombre,
                $identidad->email,
                'admin',
                [],
            );
        }
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

        $this->libroPersonal->ejecutar($identidad->id);
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

    /** @return list<Identidad> */
    private function candidatasDe(string $identificador): array
    {
        if (str_contains($identificador, '@')) {
            return $this->identidades->listarPorEmail($identificador);
        }
        $porAlias = $this->identidades->porAlias($identificador);

        return $porAlias !== null ? [$porAlias] : [];
    }
}
