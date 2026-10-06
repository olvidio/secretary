<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\CifradorSecretos;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
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
            throw new InvalidArgumentException(_("Identidad no encontrada"));
        }
        if ($this->identidades->totpConfirmado($identidadId)) {
            throw new InvalidArgumentException(_("El segundo factor ya está confirmado"));
        }
        $secreto = TotpRfc6238::secretoAleatorio();
        $this->identidades->guardarTotp($identidadId, $this->cifrador->cifrar($secreto), null);

        return [
            'secreto' => $secreto,
            'uri' => TotpRfc6238::otpauthUri($this->etiqueta($identidad), $secreto),
            'email' => $identidad->email,
        ];
    }

    private function etiqueta(Identidad $identidad): string
    {
        if ($identidad->esAdmin) {
            $nombre = _('Administrador');
        } elseif (trim($identidad->nombre) !== '') {
            $nombre = trim($identidad->nombre);
        } elseif ($identidad->alias !== null && trim($identidad->alias) !== '') {
            $nombre = trim($identidad->alias);
        } else {
            $nombre = strstr($identidad->email, '@', true) ?: $identidad->email;
        }

        return $nombre . ' - ' . $identidad->email;
    }
}
