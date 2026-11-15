<?php

declare(strict_types=1);

namespace src\administracion\infrastructure\http;

use InvalidArgumentException;
use src\ayuda\application\ListarPreguntasAyudaPendientes;
use src\ayuda\application\RepasarPreguntasAyuda;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AdminAyudaController
{
    public function __construct(
        private readonly ListarPreguntasAyudaPendientes $pendientes,
        private readonly RepasarPreguntasAyuda $repasar,
    ) {
    }

    public function listar(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok($this->pendientes->comoJson());
    }

    /** Cierra una pendiente (`pregunta`) o todas (`todas`) como cubierta o descartada. */
    public function repasar(Request $request, array $vars = []): Response
    {
        $estado = (string) $request->input('estado', '');
        try {
            $cerradas = filter_var($request->input('todas', false), FILTER_VALIDATE_BOOLEAN)
                ? $this->repasar->todas($estado)
                : $this->repasar->una((string) $request->input('pregunta', ''), $estado);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok(['cerradas' => $cerradas] + $this->pendientes->comoJson());
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
