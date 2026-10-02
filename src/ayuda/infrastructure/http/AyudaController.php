<?php

declare(strict_types=1);

namespace src\ayuda\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\services\TipoEntidad;
use src\ayuda\application\ListarTemasAyuda;
use src\ayuda\application\ResponderPreguntaAyuda;
use src\ayuda\domain\value_objects\AmbitoManual;
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
        return ContestarJson::ok(['temas' => $this->temas->ejecutar($this->ambito($request))]);
    }

    public function preguntar(Request $request, array $vars = []): Response
    {
        $identidadId = isset($request->session['identidad_id'])
            ? (int) $request->session['identidad_id']
            : null;
        $idioma = (string) ($request->session['idioma'] ?? 'es');
        $ambito = $this->ambito($request);
        try {
            $respuesta = $this->responder->ejecutar(
                (string) $request->input('pregunta', ''),
                $identidadId,
                $idioma,
                $ambito,
            );
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        } catch (RuntimeException $e) {
            return ContestarJson::error($e->getMessage(), 503);
        }
        $titulos = $this->temas->titulos($ambito);
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

    private function ambito(Request $request): AmbitoManual
    {
        if (($request->session['nivel'] ?? '') === 'persona') {
            return AmbitoManual::persona();
        }
        $id = isset($request->session['centro_id']) ? (int) $request->session['centro_id'] : 0;
        $centro = $id > 0 ? $this->centros->porId($id) : null;
        if ($centro === null) {
            return AmbitoManual::centroN();
        }
        if (CatalogoPlanesContables::esCentroSg($centro->planContableCodigo)) {
            return AmbitoManual::centroSg();
        }
        if (CatalogoPlanesContables::esClub($centro->planContableCodigo)) {
            return $centro->tipo === TipoEntidad::FUNDACION
                ? AmbitoManual::fundacion()
                : AmbitoManual::asociacion();
        }

        return AmbitoManual::centroN();
    }
}
