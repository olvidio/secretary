<?php

declare(strict_types=1);

namespace src\personal\application;

use src\apuntes\domain\contracts\PlantillaApunteRepository;
use src\personal\domain\services\ResolverCategoriaPlantillaPersonal;

final class ListarPlantillasCentroPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly PlantillaApunteRepository $plantillas,
        private readonly ResolverCategoriaPlantillaPersonal $categoriaPlantilla,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $vistas = [];
        $out = [];
        foreach ($this->plantillas->listar($ctx->centroId, 'P') as $plantilla) {
            if ($plantilla->id === null || !$plantilla->activa) {
                continue;
            }
            if (isset($vistas[$plantilla->id])) {
                continue;
            }
            $vistas[$plantilla->id] = true;
            try {
                $categoria = $this->categoriaPlantilla->ejecutar(
                    $ctx->centroId,
                    $ctx->personaId,
                    $plantilla->id,
                );
            } catch (\InvalidArgumentException) {
                continue;
            }
            $out[] = [
                'id' => $plantilla->id,
                'nombre' => $plantilla->nombre,
                'etiqueta' => $plantilla->etiqueta(),
                'categoria_id' => $categoria->id,
                'categoria_codigo' => $categoria->codigo,
            ];
        }

        return $out;
    }
}
