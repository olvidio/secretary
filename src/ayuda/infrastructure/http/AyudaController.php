<?php

declare(strict_types=1);

namespace src\ayuda\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\ambito\domain\contracts\CentroRepository;
use src\ayuda\application\ListarTemasAyuda;
use src\ayuda\application\ResponderPreguntaAyuda;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AyudaController
{
    public function __construct(
        private readonly ResponderPreguntaAyuda $responder,
        private readonly ListarTemasAyuda $temas,
        private readonly CentroRepository $centros,
    ) {
    }

    public function listarTemas(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['temas' => $this->temas->ejecutar($this->sinResumen613($request))]);
    }

    public function preguntar(Request $request, array $vars = []): Response
    {
        $identidadId = isset($request->session['identidad_id'])
            ? (int) $request->session['identidad_id']
            : null;
        $idioma = (string) ($request->session['idioma'] ?? 'es');
        try {
            $sinResumen613 = $this->sinResumen613($request);
            $respuesta = $this->responder->ejecutar(
                (string) $request->input('pregunta', ''),
                $identidadId,
                $idioma,
                $sinResumen613,
            );
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        } catch (RuntimeException $e) {
            return ContestarJson::error($e->getMessage(), 503);
        }
        $titulos = $this->temas->titulos($sinResumen613);
        $fuentes = [];
        foreach ($respuesta->fuentes as $clave) {
            $fuentes[] = ['clave' => $clave, 'titulo' => $titulos[$clave] ?? $clave];
        }

        return ContestarJson::ok([
            'respuesta' => $respuesta->texto,
            'fuentes' => $fuentes,
            'origen' => $respuesta->origen->value,
            'resuelta' => $respuesta->resuelta,
        ]);
    }

    private function sinResumen613(Request $request): bool
    {
        $id = isset($request->session['centro_id']) ? (int) $request->session['centro_id'] : 0;
        if ($id <= 0) {
            return false;
        }
        $centro = $this->centros->porId($id);

        return $centro !== null && CatalogoPlanesContables::esClub($centro->planContableCodigo);
    }
}
