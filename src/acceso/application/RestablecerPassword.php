<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\services\HuellaToken;

final class RestablecerPassword
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly EtiquetaCuentaIdentidad $etiquetas,
    ) {
    }

    /**
     * @return array{alias: string, etiqueta: string}
     */
    public function cuenta(string $token, ?DateTimeImmutable $ahora = null): array
    {
        $identidad = $this->identidadDeToken($token, $ahora ?? new DateTimeImmutable());
        $alias = $identidad->alias ?? $identidad->email;

        return [
            'alias' => $alias,
            'etiqueta' => $this->etiquetas->ejecutar($identidad),
        ];
    }

    public function ejecutar(string $token, string $nueva, string $confirmacion, ?DateTimeImmutable $ahora = null): void
    {
        $ahora ??= new DateTimeImmutable();
        $enClaro = trim($token);
        $identidad = $this->identidadDeToken($enClaro, $ahora);
        $nueva = trim($nueva);
        $confirmacion = trim($confirmacion);
        if (strlen($nueva) < 6) {
            throw new InvalidArgumentException(_('La contraseña nueva debe tener al menos 6 caracteres'));
        }
        if ($nueva !== $confirmacion) {
            throw new InvalidArgumentException(_('Las contraseñas nuevas no coinciden'));
        }
        $aplicada = $this->identidades->aplicarPasswordRestablecida(
            (int) $identidad->id,
            password_hash($nueva, PASSWORD_DEFAULT),
            HuellaToken::de($enClaro),
        );
        if (!$aplicada) {
            throw new InvalidArgumentException(_('El enlace no es válido o ha caducado. Solicite otro.'));
        }
    }

    private function identidadDeToken(string $token, DateTimeImmutable $ahora): Identidad
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException(_('El enlace no es válido o ha caducado. Solicite otro.'));
        }
        $fila = $this->identidades->porTokenRestablecerPassword(HuellaToken::de($token));
        if ($fila === null || $fila['expira'] <= $ahora) {
            throw new InvalidArgumentException(_('El enlace no es válido o ha caducado. Solicite otro.'));
        }
        $identidad = $this->identidades->porId($fila['identidad_id']);
        if ($identidad === null || $identidad->id === null || !$identidad->activo) {
            throw new InvalidArgumentException(_('El enlace no es válido o ha caducado. Solicite otro.'));
        }

        return $identidad;
    }
}
