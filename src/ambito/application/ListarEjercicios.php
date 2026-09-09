<?php

declare(strict_types=1);

namespace src\ambito\application;

use src\ambito\domain\contracts\EjercicioRepository;
use src\asientos\domain\contracts\AsientoRepository;

final class ListarEjercicios
{
    public function __construct(
        private readonly EjercicioRepository $repo,
        private readonly AsientoRepository $asientos,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(int $centroId): array
    {
        $lista = $this->repo->listarDeCentro($centroId);
        $abierto = $this->repo->abiertoDe($centroId);
        $out = [];

        foreach ($lista as $e) {
            $row = $e->toArray();
            $id = $e->id;
            $asientosApertura = $id !== null ? $this->asientos->contarApertura($id) : 0;
            $posterior = $id !== null ? $this->repo->posteriorConAnteriorId($id) : null;

            $row['asientos_apertura'] = $asientosApertura;
            $row['puede_cerrar'] = $e->estado === 'abierto';
            $row['puede_regenerar_apertura'] = $e->estado === 'abierto'
                && $e->ejercicioAnteriorId !== null;
            $row['puede_reabrir'] = false;
            if ($e->estado === 'cerrado' && $id !== null) {
                if ($posterior !== null) {
                    $row['puede_reabrir'] = $posterior->id !== null
                        && $this->asientos->contarNoApertura($posterior->id) === 0;
                } elseif ($e->ejercicioAnteriorId !== null && $abierto === null) {
                    $anterior = $this->repo->porId($e->ejercicioAnteriorId);
                    $row['puede_reabrir'] = $anterior !== null && $anterior->estado === 'cerrado';
                }
            }
            $out[] = $row;
        }

        return $out;
    }
}
