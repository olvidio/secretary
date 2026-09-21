<?php

declare(strict_types=1);

namespace src\apuntes\infrastructure\http;

use InvalidArgumentException;
use src\apuntes\application\CategorizarMovimientoBancoCentro;
use src\apuntes\application\ImportarCsvBancoCentro;
use src\apuntes\application\ListarPendientesBancoCentro;
use src\apuntes\application\PreferenciaBancoCentro;
use src\personal\domain\services\CatalogoBancosCsv;
use src\personal\infrastructure\excel\ExtractoCaixaBankDesdeArchivo;
use src\personal\infrastructure\http\RecibirFicheroExtractoBanco;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class BancoCentroController
{
    public function __construct(
        private readonly ImportarCsvBancoCentro $importar,
        private readonly ListarPendientesBancoCentro $pendientes,
        private readonly CategorizarMovimientoBancoCentro $categorizar,
        private readonly PreferenciaBancoCentro $preferenciaBanco,
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
        ]);
    }

    public function importar(Request $request, array $vars = []): Response
    {
        $banco = trim((string) $request->input('banco', ''));
        $fisicaRaw = trim((string) $request->input('cuenta_fisica_id', ''));
        $fisicaId = $fisicaRaw !== '' ? (int) $fisicaRaw : null;
        $temporal = null;
        try {
            $recibido = RecibirFicheroExtractoBanco::recibir($request, $banco);
            $temporal = $recibido['temporal'];
            if ($recibido['csv'] !== null) {
                $res = $this->importar->ejecutar($banco, $fisicaId, $recibido['csv']);
            } else {
                $filas = ExtractoCaixaBankDesdeArchivo::filas((string) $temporal);
                $res = $this->importar->ejecutar($banco, $fisicaId, filasExcel: $filas);
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
            $filaId = (int) ($datos['fila_id'] ?? 0);
            if ($filaId <= 0) {
                throw new InvalidArgumentException(_('Movimiento no indicado'));
            }
            if (!empty($datos['cambiar_a_p'])) {
                $this->categorizar->asignarConceptoP(
                    $filaId,
                    (string) ($datos['concepto_codigo'] ?? ''),
                    (string) ($datos['iniciales'] ?? ''),
                    $obs,
                );
            } elseif (!empty($datos['traspaso_caja'])) {
                $this->categorizar->traspasoACaja($filaId, $obs);
            } elseif (!empty($datos['otra_contabilidad'])) {
                $this->categorizar->otraContabilidad($filaId, $obs);
            } else {
                $this->categorizar->asignarConceptoG(
                    $filaId,
                    (string) ($datos['concepto_codigo'] ?? ''),
                    isset($datos['iniciales']) ? (string) $datos['iniciales'] : null,
                    $obs,
                );
            }
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage(), 400);
        }

        return ContestarJson::ok(['ok' => true]);
    }
}
