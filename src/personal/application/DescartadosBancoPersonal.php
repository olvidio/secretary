<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use PDO;
use src\importacion\domain\value_objects\LineaExtractoBanco;
use src\personal\domain\contracts\BancoImportRepository;
use src\shared\domain\value_objects\Dinero;
use Throwable;

/**
 * Movimientos del extracto cuyo apunte se borró en el libro personal. Conservan
 * la huella (no vuelven a entrar al reimportar); recuperarlos los vuelve a
 * anotar en «Por categorizar».
 */
final class DescartadosBancoPersonal
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ResolverPersonaActual $ambito,
        private readonly BancoImportRepository $filas,
        private readonly AsentarLineaBancoPersonal $asentar,
        private readonly AsegurarPlanPersonal $plan,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        return $this->filas->descartadas($this->ambito->ejecutar()->personaId);
    }

    public function recuperar(int $filaId): void
    {
        $ctx = $this->ambito->ejecutar();
        $fila = null;
        foreach ($this->filas->descartadas($ctx->personaId) as $f) {
            if ($f['fila_id'] === $filaId) {
                $fila = $f;
                break;
            }
        }
        if ($fila === null) {
            throw new InvalidArgumentException(_('Ese movimiento no está entre los descartados'));
        }
        $this->plan->ejecutar($ctx->centroId, $ctx->personaId);
        $linea = new LineaExtractoBanco(
            $fila['fecha'],
            Dinero::fromInput($fila['importe'])->toCents(),
            $fila['concepto'],
            $fila['huella'],
        );
        $this->pdo->beginTransaction();
        try {
            $tesoreriaId = $this->asentar->tesoreriaBanco($ctx->centroId, $ctx->personaId);
            $asientoId = $this->asentar->ejecutar($ctx->centroId, $ctx->personaId, $tesoreriaId, $linea);
            if (!$this->filas->recuperar($ctx->personaId, $filaId, $asientoId)) {
                throw new InvalidArgumentException(_('Ese movimiento no está entre los descartados'));
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
