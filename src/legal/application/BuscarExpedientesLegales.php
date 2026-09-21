<?php

declare(strict_types=1);

namespace src\legal\application;

use InvalidArgumentException;
use src\legal\domain\contracts\AceptacionLegalRepository;

final class BuscarExpedientesLegales
{
    public function __construct(
        private readonly AceptacionLegalRepository $aceptaciones,
    ) {
    }

    /**
     * @return list<array{
     *     id: int,
     *     email: string,
     *     alias: ?string,
     *     nombre: string,
     *     es_admin: bool,
     *     email_verificado_at: ?string,
     *     aceptaciones: int,
     *     primera_aceptacion: ?string,
     *     ultima_aceptacion: ?string
     * }>
     */
    public function ejecutar(string $consulta): array
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            throw new InvalidArgumentException(_('Indique correo, alias o identificador de usuario'));
        }
        if (strlen($consulta) < 2 && !ctype_digit($consulta)) {
            throw new InvalidArgumentException(_('La búsqueda debe tener al menos 2 caracteres'));
        }

        return $this->aceptaciones->buscarUsuarios($consulta);
    }
}
