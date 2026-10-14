<?php

declare(strict_types=1);

namespace src\disponible\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\domain\contracts\CuentaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\disponible\domain\services\ConstructorAsientoAsignacion;
use src\disponible\domain\services\ConstructorAsientoLiquidacionCc;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\entity\Remesa;

/** Asientos P al aceptar remesa: partidas 7 consumidas y liquidación c/c (antes iban al confirmar destinos). */
final class ApuntarLaboresConsumidosRemesa
{
    public function __construct(
        private readonly CuentaRepository $cuentas,
        private readonly AsientoRepository $asientos,
        private readonly PersonaRepository $personas,
        private readonly AsegurarCuentaCorrientePersona $asegurarCc,
    ) {
    }

    /**
     * @param list<array{linea_id:int, importe_cents:int}> $consumos
     * @param list<array{id:int, codigo_maestro:string}> $pendientesAntes
     */
    public function ejecutar(
        Remesa $remesa,
        DateTimeImmutable $fecha,
        array $consumos,
        array $pendientesAntes,
    ): void {
        if ($consumos === [] || $remesa->id === null) {
            return;
        }
        $porLinea = [];
        foreach ($pendientesAntes as $p) {
            $porLinea[(int) $p['id']] = (string) $p['codigo_maestro'];
        }
        $porCodigo = [];
        $total = 0;
        foreach ($consumos as $c) {
            $cents = (int) $c['importe_cents'];
            if ($cents <= 0) {
                continue;
            }
            $cod = $porLinea[(int) $c['linea_id']] ?? null;
            if ($cod === null) {
                continue;
            }
            $porCodigo[$cod] = ($porCodigo[$cod] ?? 0) + $cents;
            $total += $cents;
        }
        if ($total === 0 || $porCodigo === []) {
            return;
        }
        $persona = $this->personas->porId($remesa->personaId);
        if ($persona === null) {
            throw new InvalidArgumentException(_('Persona no encontrada'));
        }
        $this->asegurarCc->ejecutar($persona);
        $cc = $this->cuentas->personalDe($remesa->centroId, $remesa->personaId);
        if ($cc === null || $cc->id === null) {
            throw new InvalidArgumentException(_('Falta la cuenta CC de la persona'));
        }
        $cuenta111 = $this->cuentas->buscar($remesa->centroId, null, 'P', '111');
        if ($cuenta111 === null || $cuenta111->id === null) {
            throw new InvalidArgumentException(_('Falta la cuenta 111 en el plan P del centro'));
        }
        $lineasAsiento = [];
        foreach ($porCodigo as $codigo => $cents) {
            $cuenta = $this->cuentas->buscar($remesa->centroId, null, 'P', $codigo);
            if ($cuenta === null || $cuenta->id === null) {
                throw new InvalidArgumentException(sprintf(_('No existe la partida %s'), $codigo));
            }
            $lineasAsiento[] = ['cuenta_id' => $cuenta->id, 'importe_cents' => $cents];
        }
        $iniciales = strtoupper($persona->iniciales);
        $remesaId = (int) $remesa->id;
        $glosaLab = sprintf('Labores remesa %s %02d/%d v%d', $iniciales, $remesa->mes, $remesa->anio, $remesa->version);
        $asLab = ConstructorAsientoAsignacion::construir(
            $remesa->ejercicioId,
            $remesa->personaId,
            $fecha,
            (int) $cuenta111->id,
            $lineasAsiento,
            $glosaLab,
            $remesaId,
        );
        if ($asLab !== null) {
            $this->asientos->guardar($asLab);
        }
        $glosaCc = sprintf('Liquidación c/c remesa %s %02d/%d v%d', $iniciales, $remesa->mes, $remesa->anio, $remesa->version);
        $asCc = ConstructorAsientoLiquidacionCc::construir(
            $remesa->ejercicioId,
            $remesa->personaId,
            $fecha,
            (int) $cuenta111->id,
            (int) $cc->id,
            $total,
            $glosaCc,
            $remesaId,
        );
        if ($asCc !== null) {
            $this->asientos->guardar($asCc);
        }
    }
}
