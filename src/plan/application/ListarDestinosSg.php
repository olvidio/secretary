<?php

declare(strict_types=1);

namespace src\plan\application;

use RuntimeException;
use src\ambito\application\ResolverAmbitoActual;
use src\plan\domain\contracts\DestinoSgRepository;
use src\plan\domain\services\DestinosCentroSg;

final class ListarDestinosSg
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly DestinoSgRepository $destinos,
    ) {
    }

    /** @return array{partidas: list<array{codigo:string,etiqueta:string,orden:int}>, siguiente: ?string} */
    public function ejecutar(): array
    {
        $centroId = $this->ambito->ejecutar()->centroId;
        if ($this->destinos->nombrados($centroId) === null) {
            throw new RuntimeException(_('Este centro no usa destinos propios'));
        }
        $partidas = $this->destinos->paraCentro($centroId);

        return [
            'partidas' => $partidas,
            'siguiente' => DestinosCentroSg::siguiente(array_column($partidas, 'codigo')),
        ];
    }
}
