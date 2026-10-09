<?php

declare(strict_types=1);

namespace src\personas\application;

use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\value_objects\RolCentro;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\services\TipoEntidad;

final class ListarCentrosDisponiblesPersona
{
    public function __construct(
        private readonly CentroRepository $centros,
        private readonly IdentidadRepository $identidades,
        private readonly EtiquetaCentroParaPersona $etiquetaCentro,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(int $identidadId): array
    {
        $out = [];
        foreach ($this->centros->listar() as $centro) {
            if ($centro->id === null || !$centro->activo || !TipoEntidad::esCentroParaPersona($centro->tipo)) {
                continue;
            }
            if (str_starts_with(strtolower($centro->codigo), 'p-')) {
                continue;
            }
            if ($this->identidades->tienePersonaEnCentro($identidadId, $centro->id)) {
                continue;
            }
            $fila = $centro->toArray();
            $fila['nombre_listado'] = $this->etiquetaCentro->ejecutar($centro);
            $rol = $this->identidades->rolEnCentro($identidadId, $centro->id);
            $fila['es_secretario'] = $rol !== null && RolCentro::puedeEscribir($rol);
            $out[] = $fila;
        }

        return $out;
    }
}
