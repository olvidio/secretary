<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\services\GeneradorTokenVerificacion;

final class GuardarEmailUsuario
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly AplicarEmailIdentidad $aplicarEmail,
        private readonly NotificarCambioEmailUsuario $notificar,
    ) {
    }

    /**
     * @return array{email: string, pendiente_confirmacion: bool}
     */
    public function ejecutar(int $identidadId, string $email): array
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(_("El correo no es válido"));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null) {
            throw new InvalidArgumentException(_("Sesión caducada"));
        }
        if ($email === strtolower(trim($identidad->email))) {
            return [
                'email' => $email,
                'pendiente_confirmacion' => $this->identidades->emailPendienteDe($identidadId) !== null,
            ];
        }
        $this->aplicarEmail->comprobarDisponible($identidadId, $email);

        if (PoliticaVerificacionEmailRegistro::confirmaAlInstante()) {
            return [
                'email' => $this->aplicarEmail->ejecutar($identidadId, $email),
                'pendiente_confirmacion' => false,
            ];
        }

        $token = GeneradorTokenVerificacion::generar();
        $this->identidades->guardarCambioEmailPendiente(
            $identidadId,
            $email,
            $token,
            (new DateTimeImmutable())->modify('+48 hours'),
        );
        $this->notificar->ejecutar($identidadId, $token);

        return [
            'email' => strtolower(trim($identidad->email)),
            'pendiente_confirmacion' => true,
        ];
    }
}
