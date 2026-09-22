<?php

declare(strict_types=1);

namespace src\administracion\application;

use DateTimeImmutable;

final class PurgarBajasCentroProgramadas
{
    public function __construct(
        private readonly \src\acceso\domain\contracts\IdentidadRepository $identidades,
        private readonly PurgarIdentidadAcceso $purgar,
    ) {
    }

    /** @return array{purga_total: int, convertidas_personal: int} */
    public function ejecutar(?DateTimeImmutable $ahora = null): array
    {
        $ahora ??= new DateTimeImmutable();
        $purgaTotal = 0;
        $convertidas = 0;
        foreach ($this->identidades->listarIdsBajaCentroVencida($ahora) as $identidadId) {
            if ($this->identidades->personasDe($identidadId) !== []) {
                $this->identidades->reactivarTrasBajaCentro($identidadId);
                $convertidas++;
                continue;
            }
            $this->purgar->ejecutar($identidadId);
            $purgaTotal++;
        }

        return ['purga_total' => $purgaTotal, 'convertidas_personal' => $convertidas];
    }
}
