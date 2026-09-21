<?php

declare(strict_types=1);

namespace src\administracion\infrastructure\http;

use InvalidArgumentException;
use src\legal\application\BuscarExpedientesLegales;
use src\legal\application\ObtenerExpedienteLegal;
use src\legal\infrastructure\html\GeneradorExpedienteLegalHtml;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AdminLegalController
{
    public function __construct(
        private readonly BuscarExpedientesLegales $buscar,
        private readonly ObtenerExpedienteLegal $expediente,
    ) {
    }

    public function buscar(Request $request, array $vars = []): Response
    {
        try {
            $consulta = trim((string) $request->query('q', ''));

            return ContestarJson::ok([
                'resultados' => $this->buscar->ejecutar($consulta),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function ver(Request $request, array $vars = []): Response
    {
        try {
            $id = (int) ($vars['id'] ?? 0);

            return ContestarJson::ok($this->expediente->ejecutar($id));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }

    public function exportar(Request $request, array $vars = []): Response
    {
        try {
            $id = (int) ($vars['id'] ?? 0);
            $datos = $this->expediente->ejecutar($id);
            $html = GeneradorExpedienteLegalHtml::documento($datos);
            $alias = $datos['usuario']['alias'] ?? $datos['usuario']['email'];
            $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $alias) ?: 'usuario';
            $fecha = (new \DateTimeImmutable($datos['generado']))->format('Ymd');

            return new Response(
                $html,
                200,
                [
                    'Content-Type' => 'text/html; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="expediente-legal-' . $nombre . '-' . $fecha . '.html"',
                ],
            );
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }
    }
}
