<?php

declare(strict_types=1);

namespace src\asientos\infrastructure\http;

use InvalidArgumentException;
use src\asientos\application\RegistrarPrestamoEntreLibros;
use src\asientos\application\RegistrarTraspasoTesoreria;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class TraspasoController
{
    public function __construct(
        private readonly RegistrarTraspasoTesoreria $traspaso,
        private readonly RegistrarPrestamoEntreLibros $prestamo,
    ) {
    }

    public function traspaso(Request $request, array $vars = []): Response
    {
        try {
            $asiento = $this->traspaso->ejecutar($request->json());

            return ContestarJson::ok(['asiento' => $asiento->toArray()]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function prestamo(Request $request, array $vars = []): Response
    {
        try {
            $resultado = $this->prestamo->ejecutar($request->json());

            return ContestarJson::ok([
                'asiento_origen' => $resultado['origen']->toArray(),
                'asiento_destino' => $resultado['destino']->toArray(),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
