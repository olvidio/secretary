<?php

declare(strict_types=1);

namespace src\personas\application;

use src\personas\domain\contracts\PersonaRepository;

final class ListarPersonas
{
    public function __construct(private readonly PersonaRepository $repo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(): array
    {
        $out = [];
        foreach ($this->repo->listar() as $p) {
            $out[] = $p->toArray();
        }

        return $out;
    }

    /**
     * Listado de la pantalla Nombres: solo el centro de sesión, con el correo
     * de acceso personal (fuera de toArray() para no tocar el golden master).
     *
     * @return list<array<string, mixed>>
     */
    public function ejecutarDeCentro(int $centroId): array
    {
        $out = [];
        foreach ($this->repo->listarDeCentro($centroId) as $p) {
            $fila = $p->toArray();
            $fila['email'] = $p->email ?? '';
            $fila['vivienda_aporta_generales'] = $p->viviendaAportaGenerales;
            $out[] = $fila;
        }

        return $out;
    }
}
