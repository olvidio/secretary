<?php

declare(strict_types=1);

namespace src\personal\infrastructure\http;

use InvalidArgumentException;
use src\personal\application\CategorizarMovimientoBanco;
use src\personal\application\ImportarCsvBanco;
use src\personal\application\ListarPendientesBanco;
use src\personal\domain\services\CatalogoBancosCsv;
use src\personal\infrastructure\excel\ExtractoCaixaBankDesdeArchivo;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class BancoPersonalController
{
    public function __construct(
        private readonly ImportarCsvBanco $importar,
        private readonly ListarPendientesBanco $pendientes,
        private readonly CategorizarMovimientoBanco $categorizar,
    ) {
    }

    public function bancos(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok(['bancos' => CatalogoBancosCsv::todos()]);
    }

    public function pendientes(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok([
            'pendientes' => $this->pendientes->ejecutar(),
            'otras' => $this->pendientes->otras(),
        ]);
    }

    public function importar(Request $request, array $vars = []): Response
    {
        $banco = trim((string) $request->input('banco', ''));
        $temporal = null;
        try {
            $recibido = RecibirFicheroExtractoBanco::recibir($request, $banco);
            $temporal = $recibido['temporal'];
            if ($recibido['csv'] !== null) {
                $res = $this->importar->ejecutar($banco, $recibido['csv']);
            } else {
                $filas = ExtractoCaixaBankDesdeArchivo::filas((string) $temporal);
                $res = $this->importar->ejecutar($banco, filas: $filas);
            }
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        } finally {
            RecibirFicheroExtractoBanco::limpiar($temporal);
        }

        return ContestarJson::ok($res);
    }

    public function categorizar(Request $request, array $vars = []): Response
    {
        $datos = $request->json();
        try {
            $asiento = $this->categorizar->ejecutar(
                (int) ($datos['asiento_id'] ?? 0),
                (int) ($datos['cuenta_id'] ?? 0),
                isset($datos['observaciones']) ? (string) $datos['observaciones'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['id' => $asiento->id]);
    }
}
