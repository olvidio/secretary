<?php

declare(strict_types=1);

namespace src\administracion\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\acceso\application\AsegurarLibroPersonalIdentidad;
use src\administracion\application\EliminarCentro;
use src\ambito\application\CrearCentro;
use src\ambito\domain\contracts\CentroRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\importacion\infrastructure\http\RecibirFicheroExcel;
use src\plan\domain\contracts\PlanContableRepository;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AdminCentroController
{
    public function __construct(
        private readonly CentroRepository $centros,
        private readonly PlanContableRepository $planes,
        private readonly CrearCentro $crear,
        private readonly EliminarCentro $eliminar,
        private readonly ImportarExcelSecretario $importar,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok([
            'centros' => array_map(
                static fn ($c) => $c->toArray(),
                array_values(array_filter(
                    $this->centros->listar(),
                    static fn ($c) => $c->tipo !== AsegurarLibroPersonalIdentidad::TIPO_CENTRO,
                )),
            ),
            'planes' => $this->planes->listar(),
        ]);
    }

    public function create(Request $request, array $vars = []): Response
    {
        try {
            $datos = $request->json();
            $resultado = $this->crear->ejecutar($datos);
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
                    'id' => $resultado['identidad']->id,
                    'alias' => $resultado['identidad']->alias,
                    'email' => $resultado['identidad']->email,
                    'nombre' => $resultado['identidad']->nombre,
                ],
                'importacion' => $importacion,
                'aviso_import' => $avisoImport,
                'centros' => array_map(
                static fn ($c) => $c->toArray(),
                array_values(array_filter(
                    $this->centros->listar(),
                    static fn ($c) => $c->tipo !== AsegurarLibroPersonalIdentidad::TIPO_CENTRO,
                )),
            ),
            ]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function delete(Request $request, array $vars = []): Response
    {
        $datos = $request->json();
        $raw = $datos['confirmar'] ?? false;
        $confirmar = $raw === true || $raw === 'true' || $raw === '1' || $raw === 1;
        try {
            $this->eliminar->ejecutar((int) ($vars['id'] ?? 0), $confirmar);

            return ContestarJson::ok([
                'centros' => array_map(
                static fn ($c) => $c->toArray(),
                array_values(array_filter(
                    $this->centros->listar(),
                    static fn ($c) => $c->tipo !== AsegurarLibroPersonalIdentidad::TIPO_CENTRO,
                )),
            ),
            ]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
