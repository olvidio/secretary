<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\disponible\application\AplicarDisponibleDeRemesa;
use src\disponible\domain\services\SobranteRemesa;
use src\personal\application\ResolverPeriodoPersonal;
use src\personal\domain\services\PeriodoPersonal;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\Remesa;
use src\remesas\domain\services\ConstructorAsientoRemesa;

final class AceptarRemesa
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly RemesaRepository $remesas,
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly EjercicioRepository $ejercicios,
        private readonly PersonaRepository $personas,
        private readonly ResolverPeriodoPersonal $periodoPersonal,
        private readonly RegistrarGastosGeneralesDeRemesa $gastosGenerales,
        private readonly RegistrarPlantillasDeRemesa $plantillasDeRemesa,
        private readonly AplicarDisponibleDeRemesa $disponible,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function ejecutar(int $id, array $datos = []): Remesa
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
        $periodo = $this->periodoPersonal->ejecutar($remesa->personaId, $remesa->anio, $remesa->mes);
        $desde = PeriodoPersonal::primerDia($remesa->anio, $remesa->mes);
        $hasta = \DateTimeImmutable::createFromFormat('!Y-m-d', $periodo['hasta']);
        if ($hasta === false) {
            throw new InvalidArgumentException('Periodo de remesa no válido');
        }
        $fecha = PeriodoPersonal::fechaAsiento($desde, $hasta, $ejercicio);
        $glosa = sprintf('Remesa %s %02d/%d v%d', $iniciales, $remesa->mes, $remesa->anio, $remesa->version);
        $lineasAsiento = $this->lineasAsiento($remesa);
        $previa = $this->remesas->aceptadaDe(
            $remesa->personaId,
            $remesa->ejercicioId,
            $remesa->anio,
            $remesa->mes,
        );
        $sustituir = self::boolFlag($datos['sustituir_disponible'] ?? false);

        $generales = $this->gastosGenerales;
        $plantillas = $this->plantillasDeRemesa;
        $this->remesas->enTransaccion(function () use (
            $remesa,
            $previa,
            $ejercicio,
            $fecha,
            $glosa,
            $cc,
            $lineasAsiento,
            $generales,
            $plantillas,
            $sustituir,
        ): void {
            if ($previa !== null && $previa->id !== null && $previa->id !== $remesa->id) {
                $this->disponible->revertir((int) $previa->id);
                $this->asientos->borrarPorRemesaId($previa->id);
                $this->remesas->marcarEstado($previa->id, 'sustituida', true);
            }
            $filtradas = $this->disponible->filtrarLineas($remesa, $lineasAsiento);
            $sobrante = SobranteRemesa::cents($filtradas);
            $asiento = ConstructorAsientoRemesa::construir(
                (int) $ejercicio->id,
                $remesa->personaId,
                $fecha,
                $glosa,
                (int) $remesa->id,
                (int) $cc->id,
                $filtradas,
            );
            if ($asiento !== null) {
                $this->asientos->guardar($asiento);
            }
            $generales->ejecutar($remesa, $fecha, (int) $remesa->id);
            $plantillas->ejecutar($remesa, $fecha, (int) $remesa->id);
            $this->disponible->ejecutar(
                $remesa,
                $fecha,
                (int) $cc->id,
                (int) $ejercicio->id,
                $sustituir,
                $sobrante,
            );
            $this->remesas->marcarEstado((int) $remesa->id, 'aceptada', true);
        });
        $aceptada = $this->remesas->porId((int) $remesa->id);
        if ($aceptada === null) {
            throw new InvalidArgumentException('No se pudo releer la remesa aceptada');
        }

        return $aceptada;
    }

    /**
     * @return list<array{cuenta_id:int, tipo:string, importe_cents:int, codigo_maestro:string}>
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
                'codigo_maestro' => $linea->codigoMaestro,
            ];
        }

        return $out;
    }

    private static function boolFlag(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string) $v));

        return in_array($s, ['1', 'true', 'si', 'sí', 'on'], true);
    }
}
