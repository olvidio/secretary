<?php

declare(strict_types=1);

namespace src\grisbi\infrastructure\http;

use InvalidArgumentException;
use RuntimeException;
use src\ambito\application\ResolverAmbitoActual;
use src\grisbi\domain\services\LectorGrisbi;
use src\grisbi\infrastructure\persistence\PdoImportadorGrisbi;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class GrisbiController
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly PdoImportadorGrisbi $importador,
        private readonly LectorGrisbi $lector,
    ) {
    }

    public function importar(Request $request, array $vars = []): Response
    {
        try {
            $ctx = $this->ambito->ejecutar();
            $info = $request->file('grisbi');
            if ($info === null || (int) ($info['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(_('Falta el fichero Grisbi (.gsb)'));
            }
            $size = (int) ($info['size'] ?? 0);
            if ($size <= 0 || $size > self::MAX_BYTES) {
                throw new InvalidArgumentException(_('El fichero Grisbi supera el tamaño máximo (8 MB)'));
            }
            $tmp = (string) ($info['tmp_name'] ?? '');
            $xml = $tmp !== '' ? file_get_contents($tmp) : false;
            if (!is_string($xml) || $xml === '') {
                throw new RuntimeException(_('No se ha podido leer el fichero'));
            }
            $libro = $this->lector->leer($xml);
            $resultado = $this->importador->ejecutar($ctx->centroId, $libro);

            return ContestarJson::ok($resultado);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        } catch (RuntimeException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function movimientos(Request $request, array $vars = []): Response
    {
        try {
            $ctx = $this->ambito->ejecutar();
            $desde = (string) ($request->query('desde') ?? '');
            $hasta = (string) ($request->query('hasta') ?? '');
            $ejercicio = ($desde === '' && $hasta === '') ? (int) ($request->query('ejercicio') ?? 0) : 0;

            $movimientos = $this->importador->listarMovimientos(
                $ctx->centroId,
                $desde,
                $hasta,
                $ejercicio,
            );

            return ContestarJson::ok([
                'movimientos' => $movimientos,
                'cuentas' => $this->importador->cuentasEditables($ctx->centroId),
                'otros' => $movimientos === []
                    ? $this->importador->ejerciciosConApuntes($ctx->centroId, $ejercicio)
                    : [],
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function editar(Request $request, array $vars = []): Response
    {
        try {
            $ctx = $this->ambito->ejecutar();
            $avisos = $this->importador->editarMovimiento($ctx->centroId, (int) ($vars['id'] ?? 0), $request->json());

            return ContestarJson::ok(['guardado' => true, 'avisos' => $avisos]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function borrar(Request $request, array $vars = []): Response
    {
        try {
            $ctx = $this->ambito->ejecutar();
            $avisos = $this->importador->borrarMovimiento($ctx->centroId, (int) ($vars['id'] ?? 0));

            return ContestarJson::ok(['borrado' => true, 'avisos' => $avisos]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
