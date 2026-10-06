<?php

declare(strict_types=1);

namespace src\administracion\application;

use src\acceso\domain\contracts\IdentidadRepository;

final class ListarUsuariosAdmin
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(): array
    {
        $out = [];
        foreach ($this->identidades->listarTodas() as $u) {
            $id = (int) $u['id'];
            $centros = [];
            foreach ($this->identidades->centrosDe($id) as $v) {
                $centros[] = [
                    'centro_id' => $v->centroId,
                    'codigo' => $v->codigo,
                    'nombre' => $v->nombre,
                    'rol' => $v->rol,
                ];
            }
            $u['centros_vinculos'] = $centros;
            $u['personas_vinculos'] = $this->identidades->personasVinculoAdminDe($id);
            $out[] = $u;
        }

        return $out;
    }
}
