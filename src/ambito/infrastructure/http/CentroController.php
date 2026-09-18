<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\acceso\application\AsegurarIdentidadCentro;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\application\VaciarDatosCentro;
use src\ambito\domain\contracts\CentroRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\importacion\infrastructure\http\RecibirFicheroExcel;
use src\legal\application\ExigirDeclaracionResponsableNombres;
use src\legal\application\LecturaAceptacion;
use src\legal\infrastructure\http\HuellaAceptacionHttp;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class CentroController
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly IdentidadRepository $identidades,
        private readonly AsegurarIdentidadCentro $asegurarUsuario,
        private readonly ImportarExcelSecretario $importar,
        private readonly VaciarDatosCentro $vaciarDatos,
        private readonly ExigirDeclaracionResponsableNombres $declaracionNombres,
    ) {
    }

    public function get(Request $request, array $vars = []): Response
    {
        $ctx = $this->ambito->ejecutar();
        $centro = $this->centros->porId($ctx->centroId);
        if ($centro === null) {
            return ContestarJson::error(_("Centro no encontrado"), 404);
        }

        return ContestarJson::ok([
            'centro' => $centro->toArray(),
            'usuarios' => $this->identidades->usuariosDeCentro($ctx->centroId),
            'planes_contables' => [],
        ]);
    }

    public function import(Request $request, array $vars = []): Response
    {
        $ctx = $this->ambito->ejecutar();
        $centro = $this->centros->porId($ctx->centroId);
        if ($centro === null) {
            return ContestarJson::error(_("Centro no encontrado"), 404);
        }
        $excel = null;
        try {
            $this->declaracionNombres->comprobar(
                LecturaAceptacion::marcada($request->input('asumo_responsable_nombres', false)),
            );
            $excel = RecibirFicheroExcel::obligatorio($request, 'excel');
            $importacion = $this->importar->ejecutar($excel, true, false, $centro->codigo);
            $this->declaracionNombres->ejecutar(
                true,
                isset($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : null,
                'nombres_import',
                HuellaAceptacionHttp::desde(
                    $request,
                    (string) ($_SESSION['idioma'] ?? 'es'),
                    null,
                    null,
                    $ctx->centroId,
                ),
            );

            return ContestarJson::ok([
                'importacion' => $importacion,
                'centro' => $centro->toArray(),
            ]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        } finally {
            RecibirFicheroExcel::limpiar($excel);
        }
    }

    public function vaciar(Request $request, array $vars = []): Response
    {
        $ctx = $this->ambito->ejecutar();
        $datos = $request->json();
        $raw = $datos['confirmar'] ?? false;
        $confirmar = $raw === true
            || $raw === 'true'
            || $raw === '1'
            || $raw === 1;
        try {
            return ContestarJson::ok($this->vaciarDatos->ejecutar($ctx->centroId, $confirmar));
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function addUsuario(Request $request, array $vars = []): Response
    {
        $ctx = $this->ambito->ejecutar();
        $datos = $request->json();
        try {
            $identidad = $this->asegurarUsuario->ejecutar(
                $ctx->centroId,
                (string) ($datos['usuario'] ?? $datos['alias'] ?? ''),
                (string) ($datos['email'] ?? ''),
                (string) ($datos['password'] ?? ''),
                (string) ($datos['nombre'] ?? ''),
                'admin',
            );
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }

        return ContestarJson::ok([
            'usuario' => [
                'id' => $identidad->id,
                'alias' => $identidad->alias,
                'email' => $identidad->email,
                'nombre' => $identidad->nombre,
            ],
            'usuarios' => $this->identidades->usuariosDeCentro($ctx->centroId),
        ]);
    }
}
