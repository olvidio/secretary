<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\remesas\domain\contracts\SaldoCcManualRepository;

final class BorrarSaldoCcManual
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly SaldoCcManualRepository $saldos,
    ) {
    }

    public function ejecutar(int $id): void
    {
        $saldo = $this->saldos->porId($id);
        if ($saldo === null || $saldo->centroId !== $this->ambito->ejecutar()->centroId) {
            throw new InvalidArgumentException(_("Saldo no encontrado"));
        }
        $this->saldos->borrar($id);
    }
}
