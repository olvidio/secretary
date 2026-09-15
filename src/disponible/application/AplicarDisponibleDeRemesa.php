<?php

declare(strict_types=1);

namespace src\disponible\application;

use DateTimeImmutable;
use src\ambito\application\AsegurarCuentaDisponiblePersona;
use src\ambito\domain\contracts\CuentaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\disponible\domain\contracts\SaldoDisponibleRepository;
use src\disponible\domain\services\ConstructorAsientoAparcamiento;
use src\disponible\domain\services\ConsumidorAsignacionesRemesa;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\entity\Remesa;

/** Al aceptar: consume 7x ya confirmadas, aparca el sobrante en DISP y actualiza la tabla. */
final class AplicarDisponibleDeRemesa
{
    public function __construct(
        private readonly CuentaRepository $cuentas,
        private readonly AsientoRepository $asientos,
        private readonly SaldoDisponibleRepository $saldos,
        private readonly AsignacionLaboresRepository $asignaciones,
        private readonly PersonaRepository $personas,
        private readonly AsegurarCuentaDisponiblePersona $asegurarDisp,
    ) {
    }

    /**
     * @param list<array{cuenta_id:int, tipo:string, importe_cents:int, codigo_maestro:string}> $lineasAsiento
     * @return list<array{cuenta_id:int, tipo:string, importe_cents:int, codigo_maestro:string}>
     */
    public function filtrarLineas(Remesa $remesa, array $lineasAsiento): array
    {
        $pendientes = $this->asignaciones->pendientesConfirmadosDePersona($remesa->centroId, $remesa->personaId);
        $aplicado = ConsumidorAsignacionesRemesa::aplicar($lineasAsiento, $pendientes);
        if ($remesa->id !== null) {
            $this->asignaciones->registrarConsumos((int) $remesa->id, $aplicado['consumos']);
        }

        return $aplicado['lineas'];
    }

    public function ejecutar(
        Remesa $remesa,
        DateTimeImmutable $fecha,
        int $ccId,
        int $ejercicioId,
        bool $sustituirPorTesoreria,
        int $sobranteCents,
    ): void {
        $persona = $this->personas->porId($remesa->personaId);
        if ($persona !== null) {
            $this->asegurarDisp->ejecutar($persona);
        }
        $disp = $this->cuentas->disponibleDe($remesa->centroId, $remesa->personaId);
        if ($disp === null || $disp->id === null) {
            return;
        }
        $iniciales = $persona !== null ? strtoupper($persona->iniciales) : '';
        $glosa = sprintf('Aparcar sobrante %s %02d/%d v%d', $iniciales, $remesa->mes, $remesa->anio, $remesa->version);
        $asiento = ConstructorAsientoAparcamiento::construir(
            $ejercicioId,
            $remesa->personaId,
            $fecha,
            (int) $remesa->id,
            $ccId,
            (int) $disp->id,
            $sobranteCents,
            $glosa,
        );
        if ($asiento !== null) {
            $this->asientos->guardar($asiento);
        }

        $fechaStr = $fecha->format('Y-m-d');
        if ($sustituirPorTesoreria && $remesa->saldoTesoreriaCents !== null) {
            $actual = $this->saldos->saldoDe($remesa->centroId, $remesa->personaId);
            $delta = $remesa->saldoTesoreriaCents - $actual;
            $this->saldos->aplicar(
                $remesa->centroId,
                $remesa->personaId,
                $delta,
                $fechaStr,
                'tesoreria',
                $ejercicioId,
                (int) $remesa->id,
                null,
                'Sustituido por tesorería enviada en la remesa',
            );

            return;
        }
        if ($sobranteCents === 0) {
            return;
        }
        $this->saldos->aplicar(
            $remesa->centroId,
            $remesa->personaId,
            $sobranteCents,
            $fechaStr,
            'remesa',
            $ejercicioId,
            (int) $remesa->id,
        );
    }

    public function revertir(int $remesaId): void
    {
        $this->asignaciones->revertirConsumosDeRemesa($remesaId);
        $this->saldos->revertirPorRemesa($remesaId);
    }
}
