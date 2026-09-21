<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\CentroBancoRepository;
use src\personal\domain\services\CatalogoBancosCsv;

final class PreferenciaBancoCentro
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroBancoRepository $bancos,
    ) {
    }

    public function leer(): ?string
    {
        $ctx = $this->ambito->ejecutar();
        $banco = $this->bancos->deCentro($ctx->centroId);
        if ($banco === null || !CatalogoBancosCsv::existe($banco)) {
            return null;
        }

        return $banco;
    }

    public function guardar(string $banco): string
    {
        $ctx = $this->ambito->ejecutar();
        $banco = strtolower(trim($banco));
        if (!CatalogoBancosCsv::existe($banco)) {
            throw new InvalidArgumentException(_('Banco no soportado'));
        }
        $this->bancos->guardar($ctx->centroId, $banco);

        return $banco;
    }
}
