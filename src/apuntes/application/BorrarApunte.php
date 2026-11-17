<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use PDO;
use src\apuntes\domain\contracts\BancoCentroImportRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\shared\domain\value_objects\Dinero;
use Throwable;

final class BorrarApunte
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly BancoCentroImportRepository $bancoImport,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Borra el apunte. Una asignación de destinos 7 antigua (anotada al confirmar
     * en Disponible) va en pareja con su liquidación de c/c: se anulan las dos o
     * ninguna. Sin $conLiquidacion devuelve el aviso que hay que confirmar antes.
     *
     * @return string|null null si se ha borrado; el texto a confirmar si falta confirmación
     */
    public function ejecutar(int $id, bool $conLiquidacion = false): ?string
    {
        $asiento = $this->asientos->porId($id);
        if ($asiento === null || $asiento->libro === 'X') {
            throw new InvalidArgumentException(_("Apunte no encontrado"));
        }
        if ($asiento->origen === 'remesa' || $asiento->tipo === 'remesa' || $asiento->remesaId !== null) {
            throw new InvalidArgumentException(_("Un asiento de remesa no se borra a mano; se corrige reenviando"));
        }
        if ($asiento->origen === 'asignacion' || $asiento->tipo === 'asignacion_cc') {
            return $this->borrarAsignacion($asiento, $conLiquidacion);
        }
        // Si venía del extracto del banco, su fila queda descartada con la huella:
        // reimportar no lo vuelve a traer, y desde Banco se puede recuperar.
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

        return null;
    }

    private function borrarAsignacion(Asiento $asiento, bool $confirmado): ?string
    {
        if ($asiento->tipo !== 'normal' || $asiento->personaId === null || $asiento->id === null) {
            throw new InvalidArgumentException(_("Una liquidación de c/c de destinos 7 no se borra sola; se borra su asignación"));
        }
        $total = 0;
        foreach ($asiento->movimientos as $mov) {
            $total += $mov->debeCents;
        }
        $liquidacion = $this->asientos->buscarLiquidacionCc(
            $asiento->ejercicioId,
            $asiento->personaId,
            $asiento->fecha->format('Y-m-d'),
            $total,
            $asiento->remesaId,
        );
        if ($liquidacion === null) {
            throw new InvalidArgumentException(_("No se encuentra la liquidación de c/c de esta asignación de destinos 7; no se borra para no descuadrar el 111."));
        }
        if (!$confirmado) {
            return sprintf(
                _("Es una asignación de destinos 7 y va con su liquidación de c/c (%s €). Se borrarán las dos: la c/c de la persona recupera el importe y el 111 no cambia. ¿Seguir?"),
                Dinero::fromCents($total)->formatEs(),
            );
        }
        $this->asientos->anularAsignacionConLiquidacion($asiento->id, $liquidacion);

        return null;
    }
}
