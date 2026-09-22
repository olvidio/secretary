<?php

declare(strict_types=1);

namespace src\acceso\application;

use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;

final class EtiquetaCuentaIdentidad
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(Identidad $identidad): string
    {
        if ($identidad->id === null) {
            return $identidad->alias ?? $identidad->email;
        }
        if ($identidad->esAdmin) {
            return _('Administrador');
        }
        $centros = $this->identidades->centrosDe($identidad->id);
        if ($centros !== []) {
            $c = $centros[0];

            return sprintf(
                '%s (%s)',
                $c->nombre !== '' ? $c->nombre : $c->codigo,
                $identidad->alias ?? _('Secretario'),
            );
        }
        if ($this->identidades->personasDe($identidad->id) !== []) {
            return _('Libro personal') . ($identidad->alias !== null ? ' (' . $identidad->alias . ')' : '');
        }

        return $identidad->alias ?? $identidad->email;
    }
}
