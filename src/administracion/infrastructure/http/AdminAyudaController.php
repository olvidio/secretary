<?php

declare(strict_types=1);

namespace src\administracion\infrastructure\http;

use src\ayuda\application\ListarPreguntasAyudaPendientes;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AdminAyudaController
{
    public function __construct(
        private readonly ListarPreguntasAyudaPendientes $pendientes,
    ) {
    }

    public function listar(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->pendientes->comoJson());
    }

    public function exportar(Request $request, array $vars = []): Response
    {
        $formato = strtolower(trim((string) $request->query('formato', 'md')));
        $fecha = gmdate('Ymd-His');
        if ($formato === 'json') {
            $cuerpo = $this->pendientes->exportarJson();

            return new Response(
                $cuerpo,
                200,
                [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="ayuda-pendientes-' . $fecha . '.json"',
                ],
            );
        }

        $cuerpo = $this->pendientes->comoMarkdown();

        return new Response(
            $cuerpo,
            200,
            [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="ayuda-pendientes-' . $fecha . '.md"',
            ],
        );
    }
}
