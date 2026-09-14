<?php

declare(strict_types=1);

namespace src\personal\application;

use src\conceptos\domain\services\CatalogoConceptos;

final class ListarConceptosGenerales
{
    /**
     * @return list<array{codigo:string,nombre:string,descripcion:string}>
     */
    public function ejecutar(): array
    {
        $out = [];
        foreach (CatalogoConceptos::todos() as $c) {
            if ($c['cuenta'] !== 'G' || $c['naturaleza'] !== 'gasto') {
                continue;
            }
            $out[] = [
                'codigo' => $c['codigo'],
                'nombre' => $c['nombre'] !== '' ? $c['nombre'] : $c['codigo'],
                'descripcion' => $c['descripcion'],
            ];
        }

        return $out;
    }
}
