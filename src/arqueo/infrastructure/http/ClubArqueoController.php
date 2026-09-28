<?php

declare(strict_types=1);

namespace src\arqueo\infrastructure\http;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\arqueo\infrastructure\persistence\PdoArqueoCuadrado;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class ClubArqueoController
{
    public function __construct(
        private readonly PdoArqueoCuadrado $cuadrados,
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
    ) {
    }

    public function estado(Request $request, array $vars = []): Response
    {
        try {
            $centroId = $this->centroClub();
            $fecha = $this->fecha((string) ($request->query('fecha') ?? date('Y-m-d')));

            return ContestarJson::ok([
                'fecha' => $fecha,
                'tesoreria' => $this->cuadrados->tesoreria($centroId, $fecha),
                'cuadrados' => $this->cuadrados->listar($centroId),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    public function cuadrar(Request $request, array $vars = []): Response
    {
        try {
            $centroId = $this->centroClub();
            $body = $request->json();
            $fecha = $this->fecha((string) ($body['fecha'] ?? ''));
            $contados = is_array($body['contados'] ?? null) ? $body['contados'] : [];
            $this->cuadrados->cuadrar($centroId, $fecha, $contados);

            return ContestarJson::ok([
                'cuadrado' => true,
                'cuadrados' => $this->cuadrados->listar($centroId),
            ]);
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }

    private function centroClub(): int
    {
        $centroId = $this->ambito->ejecutar()->centroId;
        $centro = $this->centros->porId($centroId);
        if ($centro === null || !CatalogoPlanesContables::esClub($centro->planContableCodigo)) {
            throw new InvalidArgumentException(_('El arqueo de periodo es de una associació'));
        }

        return $centroId;
    }

    private function fecha(string $fecha): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            throw new InvalidArgumentException(_('Fecha no válida'));
        }

        return $fecha;
    }
}
