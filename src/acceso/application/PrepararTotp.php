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
            'uri' => TotpRfc6238::otpauthUri($this->etiqueta($identidadId, $identidad), $secreto),
            'email' => $identidad->email,
        ];
    }

    private function etiqueta(int $identidadId, Identidad $identidad): string
    {
        $nombres = [];
        foreach ($this->identidades->centrosDe($identidadId) as $centro) {
            $nombres[] = $centro->nombre !== '' ? $centro->nombre : $centro->codigo;
        }
        if ($nombres !== []) {
            $sitio = implode(', ', $nombres);
        } elseif ($identidad->esAdmin) {
            $sitio = _('Administrador');
        } else {
            $sitio = $identidad->alias ?? _('Libro personal');
        }

        return $sitio . ' - ' . $identidad->email;
    }
}
