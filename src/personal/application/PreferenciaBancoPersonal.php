<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use src\personal\domain\contracts\PersonalBancoRepository;
use src\personal\domain\services\CatalogoBancosCsv;

final class PreferenciaBancoPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly PersonalBancoRepository $bancos,
    ) {
    }

    public function leer(): ?string
    {
        $ctx = $this->ambito->ejecutar();
        $banco = $this->bancos->dePersona($ctx->personaId);
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
            throw new InvalidArgumentException('Banco no soportado: elija uno de la lista');
        }
        $this->bancos->guardar($ctx->personaId, $banco);

        return $banco;
    }
}
