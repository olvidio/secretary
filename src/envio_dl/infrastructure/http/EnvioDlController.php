<?php

declare(strict_types=1);

namespace src\envio_dl\infrastructure\http;

use InvalidArgumentException;
use src\envio_dl\application\ConfirmarEnvioDl;
use src\envio_dl\application\ProponerEnvioDl;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class EnvioDlController
{
    public function __construct(
        private readonly ProponerEnvioDl $proponer,
        private readonly ConfirmarEnvioDl $confirmar,
    ) {
    }

    public function proponer(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->proponer->ejecutar($request->json()));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function confirmar(Request $request, array $vars): Response
    {
        try {
            return ContestarJson::ok(['envio' => $this->confirmar->ejecutar((int) ($vars['id'] ?? 0))]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
