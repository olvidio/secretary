<?php

declare(strict_types=1);

namespace src\shared\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\shared\application\BorrarCopiaSeguridad;
use src\shared\application\CrearCopiaSeguridad;
use src\shared\application\ListarCopiasSeguridad;
use src\shared\application\RestaurarCopiaSeguridad;
use src\shared\domain\exceptions\LimiteCopiasAlcanzado;
use src\shared\infrastructure\persistence\AlmacenCopiasSeguridad;

final class CopiaSeguridadController
{
    public function __construct(
        private readonly ListarCopiasSeguridad $listar,
        private readonly CrearCopiaSeguridad $crear,
        private readonly RestaurarCopiaSeguridad $restaurar,
        private readonly BorrarCopiaSeguridad $borrar,
        private readonly AlmacenCopiasSeguridad $almacen,
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
        $borrarMasAntigua = !empty($request->json()['borrar_mas_antigua']);
        try {
            return ContestarJson::ok($this->crear->ejecutar($borrarMasAntigua));
        } catch (LimiteCopiasAlcanzado $e) {
            return ContestarJson::error($e->getMessage(), 400, ['codigo' => LimiteCopiasAlcanzado::CODIGO]);
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
            $ruta = $this->almacen->rutaDeNombre($nombre);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 404);
        }

        return new Response(
            (string) file_get_contents($ruta),
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . basename($ruta) . '"',
                'Content-Length' => (string) filesize($ruta),
            ],
        );
    }

    public function restore(Request $request, array $vars = []): Response
    {
        $subido = RecibirFicheroCopia::opcional($request);
        try {
            $this->restaurar->ejecutar($request->json(), $subido);
        } catch (InvalidArgumentException $e) {
            RecibirFicheroCopia::limpiar($subido);

            return ContestarJson::error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            RecibirFicheroCopia::limpiar($subido);

            return ContestarJson::error($e->getMessage());
        } finally {
            RecibirFicheroCopia::limpiar($subido);
        }

        return ContestarJson::ok([
            'database' => $this->almacen->database(),
            'mensaje' => _("Restauración completada. Compruebe el esquema con db:status si procede."),
        ]);
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

        return ContestarJson::ok(['mensaje' => _("Copia borrada del servidor.")]);
    }
}
