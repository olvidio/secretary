<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\Remesa;
use src\remesas\domain\services\ConstructorAsientoRemesa;
use src\remesas\domain\services\PeriodoMesRemesa;

final class AceptarRemesa
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly RemesaRepository $remesas,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly EjercicioRepository $ejercicios,
        private readonly PersonaRepository $personas,
    ) {
    }

    public function ejecutar(int $id): Remesa
    {
        $remesa = $this->remesas->porId($id);
        $ctx = $this->ambito->ejecutar();
        if ($remesa === null || $remesa->centroId !== $ctx->centroId) {
            throw new InvalidArgumentException('Remesa no encontrada');
        }
        if ($remesa->estado !== 'enviada' || $remesa->id === null) {
            throw new InvalidArgumentException('Solo se puede aceptar una remesa enviada');
        }
        $ejercicio = $this->ejercicios->porId($remesa->ejercicioId);
        if ($ejercicio === null || $ejercicio->estado !== 'abierto' || $ejercicio->id === null) {
            throw new InvalidArgumentException('El ejercicio de la remesa está cerrado');
        }
        $cc = $this->cuentas->personalDe($remesa->centroId, $remesa->personaId);
        if ($cc === null || $cc->id === null) {
            throw new InvalidArgumentException('Falta la cuenta personal del centro para esa persona');
        }
        $persona = $this->personas->porId($remesa->personaId);
        $iniciales = $persona !== null ? strtoupper($persona->iniciales) : '';
        $fecha = PeriodoMesRemesa::fechaAsiento($remesa->anio, $remesa->mes, $ejercicio);
        $glosa = sprintf('Remesa %s %02d/%d v%d', $iniciales, $remesa->mes, $remesa->anio, $remesa->version);
        $lineasAsiento = $this->lineasAsiento($remesa);
        $previa = $this->remesas->aceptadaDe(
            $remesa->personaId,
            $remesa->ejercicioId,
            $remesa->anio,
            $remesa->mes,
        );

        $this->remesas->enTransaccion(function () use ($remesa, $previa, $ejercicio, $fecha, $glosa, $cc, $lineasAsiento): void {
            if ($previa !== null && $previa->id !== null && $previa->id !== $remesa->id) {
                $this->asientos->borrarPorRemesaId($previa->id);
                $this->remesas->marcarEstado($previa->id, 'sustituida', true);
            }
            $asiento = ConstructorAsientoRemesa::construir(
                (int) $ejercicio->id,
                $remesa->personaId,
                $fecha,
                $glosa,
                (int) $remesa->id,
                (int) $cc->id,
                $lineasAsiento,
            );
            if ($asiento !== null) {
                $this->asientos->guardar($asiento);
            }
            $this->remesas->marcarEstado((int) $remesa->id, 'aceptada', true);
        });
        $aceptada = $this->remesas->porId((int) $remesa->id);
        if ($aceptada === null) {
            throw new InvalidArgumentException('No se pudo releer la remesa aceptada');
        }

        return $aceptada;
    }

    /**
     * @return list<array{cuenta_id:int, tipo:string, importe_cents:int}>
     */
    private function lineasAsiento(Remesa $remesa): array
    {
        $out = [];
        foreach ($remesa->lineas as $linea) {
            $cuenta = $this->cuentas->buscar($remesa->centroId, null, 'P', $linea->codigoMaestro);
            if ($cuenta === null || $cuenta->id === null) {
                throw new InvalidArgumentException(
                    'No existe en el plan P del centro el concepto ' . $linea->codigoMaestro
                );
            }
            if (!in_array($cuenta->tipo, ['ingreso', 'gasto'], true)) {
                throw new InvalidArgumentException('El concepto ' . $linea->codigoMaestro . ' no es imputable como ingreso o gasto');
            }
            $out[] = [
                'cuenta_id' => $cuenta->id,
                'tipo' => $cuenta->tipo,
                'importe_cents' => $linea->importeCents,
            ];
        }

        return $out;
    }
}
