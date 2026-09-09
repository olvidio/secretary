<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\acceso\application\AsegurarIdentidadCentro;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\application\CrearCentro;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\application\VaciarDatosCentro;
use src\ambito\domain\contracts\CentroRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\importacion\infrastructure\http\RecibirFicheroExcel;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class CentroController
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly IdentidadRepository $identidades,
        private readonly CrearCentro $crear,
        private readonly AsegurarIdentidadCentro $asegurarUsuario,
        private readonly ImportarExcelSecretario $importar,
        private readonly VaciarDatosCentro $vaciarDatos,
    ) {
    }

    public function get(Request $request, array $vars = []): Response
    {
        $ctx = $this->ambito->ejecutar();
        $centro = $this->centros->porId($ctx->centroId);
        if ($centro === null) {
            return ContestarJson::error('Centro no encontrado', 404);
        }

        return ContestarJson::ok([
            'centro' => $centro->toArray(),
            'usuarios' => $this->identidades->usuariosDeCentro($ctx->centroId),
        ]);
    }

    public function create(Request $request, array $vars = []): Response
    {
        try {
            $resultado = $this->crear->ejecutar($request->json());
            $identidad = $resultado['identidad'];
            $importacion = null;
            $avisoImport = null;
            $excel = RecibirFicheroExcel::opcional($request, 'excel');
            if ($excel !== null) {
                try {
                    $importacion = $this->importar->ejecutar(
                        $excel,
                        true,
                        false,
                        $resultado['centro']->codigo,
                    );
                } catch (\Throwable $e) {
                    $avisoImport = $e->getMessage();
                } finally {
                    RecibirFicheroExcel::limpiar($excel);
                }
            }

            return ContestarJson::ok([
                'centro' => $resultado['centro']->toArray(),
                'ejercicio' => $resultado['ejercicio']->toArray(),
                'usuario' => [
                    'id' => $identidad->id,
                    'alias' => $identidad->alias,
                    'email' => $identidad->email,
                    'nombre' => $identidad->nombre,
                ],
                'importacion' => $importacion,
                'aviso_import' => $avisoImport,
            ]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function import(Request $request, array $vars = []): Response
    {
        $ctx = $this->ambito->ejecutar();
        $centro = $this->centros->porId($ctx->centroId);
        if ($centro === null) {
            return ContestarJson::error('Centro no encontrado', 404);
        }
        $excel = null;
        try {
            $excel = RecibirFicheroExcel::obligatorio($request, 'excel');
            $importacion = $this->importar->ejecutar($excel, true, false, $centro->codigo);

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
