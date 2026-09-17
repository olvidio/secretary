<?php

declare(strict_types=1);

namespace src\personal\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\personal\application\BorrarCopiaPersonal;
use src\personal\application\CrearCopiaPersonal;
use src\personal\application\ListarCopiasPersonal;
use src\personal\application\RestaurarCopiaPersonal;
use src\personal\application\ResolverPersonaActual;
use src\personal\infrastructure\persistence\AlmacenCopiasPersonal;
use src\personal\infrastructure\persistence\RutasCopiasPersonal;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class CopiaPersonalController
{
    public function __construct(
        private readonly ListarCopiasPersonal $listar,
        private readonly CrearCopiaPersonal $crear,
        private readonly RestaurarCopiaPersonal $restaurar,
        private readonly BorrarCopiaPersonal $borrar,
        private readonly ResolverPersonaActual $ambito,
        private readonly PersonaRepository $personas,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->listar->ejecutar());
        } catch (RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function backup(Request $request, array $vars = []): Response
    {
        try {
            return ContestarJson::ok($this->crear->ejecutar());
        } catch (RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function descargar(Request $request, array $vars = []): Response
    {
        $nombre = trim((string) ($request->query('fichero') ?? ''));
        if ($nombre === '') {
            return ContestarJson::error(_("Indique el fichero a descargar"), 400);
        }
        try {
            $ctx = $this->ambito->ejecutar();
            $persona = $this->personas->porId($ctx->personaId);
            if ($persona === null) {
                throw new InvalidArgumentException(_("Persona no encontrada"));
            }
            $almacen = new AlmacenCopiasPersonal(
                RutasCopiasPersonal::directorio(),
                $ctx->personaId,
                $persona->iniciales,
            );
            $ruta = $almacen->rutaDeNombre($nombre);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }

        return new Response(
            (string) file_get_contents($ruta),
            200,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . basename($ruta) . '"',
                'Content-Length' => (string) filesize($ruta),
            ],
        );
    }

    public function restore(Request $request, array $vars = []): Response
    {
        $subido = RecibirFicheroCopiaPersonal::opcional($request);
        try {
            $resultado = $this->restaurar->ejecutar($request->json(), $subido);
        } catch (InvalidArgumentException $e) {
            RecibirFicheroCopiaPersonal::limpiar($subido);

            return ContestarJson::error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            RecibirFicheroCopiaPersonal::limpiar($subido);

            return ContestarJson::error($e->getMessage());
        } finally {
            RecibirFicheroCopiaPersonal::limpiar($subido);
        }

        return ContestarJson::ok($resultado);
    }

    public function borrar(Request $request, array $vars = []): Response
    {
        try {
            $this->borrar->ejecutar($request->json());
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok(['mensaje' => _("Copia borrada.")]);
    }
}
