<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\CifradorSecretos;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\services\TotpRfc6238;

final class ConfirmarTotp
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly CifradorSecretos $cifrador,
        private readonly string $pimiento,
    ) {
    }

    /**
     * @return list<string> códigos de recuperación en claro (una sola vez)
     */
    public function ejecutar(int $identidadId, string $codigo, ?DateTimeImmutable $ahora = null): array
    {
        $ahora ??= new DateTimeImmutable();
        $cifrado = $this->identidades->totpSecretoCifrado($identidadId);
        if ($cifrado === null) {
            throw new InvalidArgumentException('No hay un secreto TOTP pendiente de confirmar');
        }
        $secreto = $this->cifrador->descifrar($cifrado);
        if (!TotpRfc6238::verificar($secreto, $codigo)) {
            throw new InvalidArgumentException('Código TOTP incorrecto');
        }
        $this->identidades->confirmarTotp($identidadId, $ahora);
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad !== null) {
            $this->identidades->registrarExito($identidad, $ahora);
        }
        $codigos = self::generarCodigos();
        $hashes = [];
        foreach ($codigos as $claro) {
            $hashes[] = hash('sha256', $claro . '|' . $this->pimiento);
        }
        $this->identidades->reemplazarRecovery($identidadId, $hashes);

        return $codigos;
    }

    /** @return list<string> */
    public static function generarCodigos(int $n = 8): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $hex = strtoupper(bin2hex(random_bytes(4)));
            $out[] = substr($hex, 0, 4) . '-' . substr($hex, 4, 4);
        }

        return $out;
    }
}
