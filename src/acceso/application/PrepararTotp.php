<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\CifradorSecretos;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\services\TotpRfc6238;

final class PrepararTotp
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly CifradorSecretos $cifrador,
    ) {
    }

    /**
     * @return array{secreto: string, uri: string, email: string}
     */
    public function ejecutar(int $identidadId): array
    {
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null) {
            throw new InvalidArgumentException('Identidad no encontrada');
        }
        if ($this->identidades->totpConfirmado($identidadId)) {
            throw new InvalidArgumentException('El segundo factor ya está confirmado');
        }
        $secreto = TotpRfc6238::secretoAleatorio();
        $this->identidades->guardarTotp($identidadId, $this->cifrador->cifrar($secreto), null);

        return [
            'secreto' => $secreto,
            'uri' => TotpRfc6238::otpauthUri($identidad->email, $secreto),
            'email' => $identidad->email,
        ];
    }
}
