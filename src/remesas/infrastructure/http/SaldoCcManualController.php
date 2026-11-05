<?php

declare(strict_types=1);

namespace src\remesas\infrastructure\http;

use InvalidArgumentException;
use src\remesas\application\BorrarSaldoCcManual;
use src\remesas\application\ListarSaldosCcManuales;
use src\remesas\application\RegistrarSaldoCcManual;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

/** Concepto 90 anotado a mano por el secretario (personas sin remesa). */
final class SaldoCcManualController
{
    public function __construct(
        private readonly ListarSaldosCcManuales $listar,
        private readonly RegistrarSaldoCcManual $registrar,
        private readonly BorrarSaldoCcManual $borrar,
    ) {
    }

    public function listar(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['saldos' => $this->listar->ejecutar()]);
    }

    public function guardar(Request $request, array $vars = []): Response
    {
        try {
            $saldo = $this->registrar->ejecutar($request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['saldo' => $saldo->toArray()]);
    }

    public function borrar(Request $request, array $vars = []): Response
    {
        try {
            $this->borrar->ejecutar((int) ($vars['id'] ?? 0));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }

        return ContestarJson::ok([]);
    }
}
