<?php

declare(strict_types=1);

namespace src\listados\infrastructure\http;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\listados\infrastructure\persistence\PdoAportacionesSg;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\http\ContestarJson;
use src\shared\infrastructure\http\Request;
use src\shared\infrastructure\http\Response;

final class AportacionesSgController
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly ConfiguracionRepository $config,
        private readonly PdoAportacionesSg $listado,
    ) {
    }

    public function list(Request $request, array $vars = []): Response
    {
        try {
            $ctx = $this->ambito->ejecutar();
            $centro = $this->centros->porId($ctx->centroId);
            if ($centro === null || !CatalogoPlanesContables::esCentroSg($centro->planContableCodigo)) {
                throw new InvalidArgumentException(_("Este listado es del plan H16s"));
            }
            $cfg = $this->config->get();
            $periodo = $cfg->periodo();

            return ContestarJson::ok($this->listado->ejecutar(
                $ctx->centroId,
                $ctx->ejercicioId,
                $periodo->fechaInicio->format('Y-m-d'),
                $periodo->fechaCorte->format('Y-m-d'),
            ));
        } catch (InvalidArgumentException $e) {
            return ContestarJson::error($e->getMessage());
        }
    }
}
