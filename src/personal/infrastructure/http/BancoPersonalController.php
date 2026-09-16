<?php

declare(strict_types=1);

namespace src\personal\infrastructure\http;

use InvalidArgumentException;
use src\personal\application\CategorizarMovimientoBanco;
use src\personal\application\ImportarCsvBanco;
use src\personal\application\ListarPendientesBanco;
use src\personal\application\ListarPlantillasCentroPersonal;
use src\personal\application\PreferenciaBancoPersonal;
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
        private readonly PreferenciaBancoPersonal $preferenciaBanco,
        private readonly ListarPlantillasCentroPersonal $plantillasCentro,
    ) {
    }

    public function bancos(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok([
            'bancos' => CatalogoBancosCsv::todos(),
            'banco' => $this->preferenciaBanco->leer(),
        ]);
    }

    public function guardarPreferencia(Request $request, array $vars = []): Response
    {
        try {
            $banco = $this->preferenciaBanco->guardar((string) ($request->json()['banco'] ?? ''));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['banco' => $banco]);
    }

    public function pendientes(Request $request, array $vars = []): Response
    {
        return ContestarJson::ok([
            'pendientes' => $this->pendientes->ejecutar(),
            'otras' => $this->pendientes->otras(),
            'plantillas' => $this->plantillasCentro->ejecutar(),
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
        try {
            $this->preferenciaBanco->guardar($banco);
        } catch (InvalidArgumentException) {
        }

        return ContestarJson::ok($res);
    }

    public function categorizar(Request $request, array $vars = []): Response
    {
        $datos = $request->json();
        try {
            $obs = isset($datos['observaciones']) ? (string) $datos['observaciones'] : null;
            $asientoId = (int) ($datos['asiento_id'] ?? 0);
            if (!empty($datos['traspaso_caja'])) {
                $asiento = $this->categorizar->traspasoACaja($asientoId, $obs);
            } elseif (!empty($datos['plantilla_id'])) {
                $asiento = $this->categorizar->conPlantilla($asientoId, (int) $datos['plantilla_id'], $obs);
            } else {
                $asiento = $this->categorizar->ejecutar(
                    $asientoId,
                    (int) ($datos['cuenta_id'] ?? 0),
                    $obs,
                );
            }
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['id' => $asiento->id]);
    }
}
