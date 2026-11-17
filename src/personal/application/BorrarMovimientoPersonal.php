<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use PDO;
use src\asientos\domain\contracts\AsientoRepository;
use src\personal\domain\contracts\BancoImportRepository;
use Throwable;

final class BorrarMovimientoPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly AsientoRepository $asientos,
        private readonly BancoImportRepository $bancoImport,
        private readonly PDO $pdo,
    ) {
    }

    public function ejecutar(int $id): void
    {
        $ctx = $this->ambito->ejecutar();
        $asiento = $this->asientos->porId($id);
        if ($asiento === null || $asiento->libro !== 'X' || $asiento->personaId !== $ctx->personaId) {
            throw new InvalidArgumentException(_("Movimiento no encontrado"));
        }
        // Si venía del extracto, su fila queda descartada con la huella: reimportar
        // no lo vuelve a traer, y desde Banco se puede recuperar.
        $this->pdo->beginTransaction();
        try {
            $this->bancoImport->descartarDeAsiento($id);
            $this->asientos->borrar($id);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
