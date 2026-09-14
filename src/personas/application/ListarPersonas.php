<?php

declare(strict_types=1);

namespace src\personas\application;

use src\ambito\domain\contracts\CentroRepository;
use src\personas\domain\contracts\PersonaRepository;

final class ListarPersonas
{
    public function __construct(
        private readonly PersonaRepository $repo,
        private readonly CentroRepository $centros,
    ) {
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
        $centro = $this->centros->porId($centroId);
        $centroNombre = $centro?->nombre ?? '';
        $centroCodigo = $centro?->codigo ?? '';
        $out = [];
        foreach ($this->repo->listarDeCentro($centroId) as $p) {
            $fila = $p->toArray();
            $fila['email'] = $p->email ?? '';
            $fila['vivienda_aporta_generales'] = $p->viviendaAportaGenerales;
            $fila['centro_id'] = $centroId;
            $fila['centro_nombre'] = $centroNombre;
            $fila['centro_codigo'] = $centroCodigo;
            $out[] = $fila;
        }

        return $out;
    }
}
