<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\services\GeneradorTokenVerificacion;
use src\acceso\domain\services\HuellaToken;

final class SolicitarRestablecerPassword
{
    public const HORAS = 2;

    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly NotificarRestablecerPassword $notificar,
        private readonly EtiquetaCuentaIdentidad $etiquetas,
    ) {
    }

    public function ejecutar(string $identificador): void
    {
        $identificador = trim($identificador);
        if ($identificador === '') {
            throw new InvalidArgumentException(_('Indique el alias o el correo'));
        }
        $candidatas = str_contains($identificador, '@')
            ? $this->identidades->listarPorEmail($identificador)
            : array_values(array_filter(
                [$this->identidades->porAlias($identificador)],
                static fn (?Identidad $identidad): bool => $identidad !== null,
            ));

        /** @var array<string, list<array{etiqueta: string, token: string}>> $porEmail */
        $porEmail = [];
        foreach ($candidatas as $identidad) {
            if ($identidad->id === null || !$identidad->activo || !$identidad->emailVerificado()) {
                continue;
            }
            $email = strtolower(trim($identidad->email));
            if ($email === '') {
                continue;
            }
            $token = GeneradorTokenVerificacion::generar();
            $expira = (new \DateTimeImmutable())->modify('+' . self::HORAS . ' hours');
            $this->identidades->guardarTokenRestablecerPassword($identidad->id, HuellaToken::de($token), $expira);
            $porEmail[$email][] = [
                'etiqueta' => $this->etiquetas->ejecutar($identidad),
                'token' => $token,
            ];
        }
        foreach ($porEmail as $email => $cuentas) {
            $this->notificar->ejecutar($email, $cuentas);
        }
    }
}
